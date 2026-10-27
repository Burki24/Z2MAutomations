import assert from 'node:assert/strict';
import test from 'node:test';
import {mkdtemp, rm, writeFile} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
import Extension from '../libs/z2m/z2m-automations.mjs';

const sourceID = '0x0000000000000001';
const targetID = '0x0000000000000002';
const rule = () => ({id: 'synthetic_rule', name: 'Synthetic rule', enabled: true,
    triggers: [{type: 'device', device: sourceID, property: 'occupancy', operator: 'eq', value: true}],
    conditions: [], actions: [{type: 'set', target: {type: 'device', id: targetID}, values: {state: 'ON'}}]});
const yaml = (rules = [rule()], revision = 1) => JSON.stringify({schema_version: 1, revision, automations: rules});
const flush = async () => { for (let i = 0; i < 20; i++) await Promise.resolve(); };

class Clock {
    time = 0;
    wall = Date.parse('2026-10-26T12:00:00Z');
    next = 0;
    timers = new Map();
    now = () => this.time;
    wallNow = () => this.wall;
    setTimeout = (callback, delay) => { const id = ++this.next; this.timers.set(id, {callback, at: this.time + delay}); return id; };
    clearTimeout = id => this.timers.delete(id);
    setInterval = (callback, delay) => { const id = ++this.next; this.timers.set(id, {callback, at: this.time + delay, interval: delay}); return id; };
    clearInterval = this.clearTimeout;
    async advance(ms) {
        const end = this.time + ms;
        while (true) {
            const entry = [...this.timers].filter(([, timer]) => timer.at <= end).sort((a, b) => a[1].at - b[1].at)[0];
            if (!entry) break;
            const [id, timer] = entry;
            this.wall += timer.at - this.time; this.time = timer.at;
            if (timer.interval) timer.at += timer.interval; else this.timers.delete(id);
            timer.callback(); await flush();
        }
        this.wall += end - this.time; this.time = end; await flush();
    }
}

async function harness(directory = null) {
    directory ??= await mkdtemp(join(tmpdir(), 'z2ma-runtime-'));
    const clock = new Clock();
    const messages = [], commands = [], logs = [];
    const device = (ID, name) => ({ID, ieeeAddr: ID, name, isDevice: () => true});
    const devices = new Map([[sourceID, device(sourceID, 'synthetic/source')], [targetID, device(targetID, 'synthetic/target')]]);
    const group = {ID: 7, name: 'synthetic/group', isDevice: () => false};
    let publishState, receive;
    const bus = {onMQTTMessage: (_owner, callback) => { receive = callback; },
        onPublishEntityState: (_owner, callback) => { publishState = callback; },
        removeListeners: () => { receive = undefined; publishState = undefined; }};
    const zigbee = {resolveEntity: id => devices.get(id), groupsIterator: () => [group].values()};
    const mqtt = {publish: async (topic, payload, options) => {
        const message = {topic, data: JSON.parse(payload), options};
        if (topic.endsWith('/set')) { commands.push(message); if (h.sendFault) throw new Error('synthetic-private-body'); }
        else messages.push(message);
    }};
    const logger = Object.fromEntries(['debug', 'info', 'warning', 'error'].map(level => [level, text => { logs.push(text); if (h.logFault) throw new Error('sink unavailable'); }]));
    const extension = new Extension(zigbee, mqtt, null, null, bus, null, null, null,
        {get: () => ({mqtt: {base_topic: 'synthetic/z2m'}})}, logger, {directory, clock});
    let sequence = 0;
    const h = {extension, directory, clock, devices, group, commands, messages, logs, sendFault: false, logFault: false,
        request: async (operation, data = {}, extra = {}) => {
            const transaction = 'runtime-' + ++sequence;
            await receive({topic: 'synthetic/z2m/symcon/automations/request/' + operation,
                message: JSON.stringify({protocol_version: 1, transaction, ...data}), ...extra});
            return messages.findLast(message => message.data.transaction === transaction)?.data;
        },
        event: async (payload, extra = {}) => { publishState?.({entity: devices.get(sourceID), payload, message: payload, ...extra}); await flush(); },
        close: async (remove = true) => { await extension.stop(); if (remove) await rm(directory, {recursive: true, force: true}); }};
    await extension.start();
    h.session = extension.status().session;
    h.store = text => h.request('store', {yaml: text, expected_revision: extension.status().stored_revision, session: h.session});
    h.activate = async (extra = {}, event = {}) => {
        const status = await h.request('status');
        return h.request('activate', {session: h.session, activation_token: status.data.activation_token,
            expected_revision: extension.status().stored_revision, checksum: extension.status().checksum, ...extra}, event);
    };
    h.deactivate = () => h.request('deactivate', {session: h.session});
    return h;
}

test('storage never executes; explicit activation executes a fresh device trigger through native set topics', async () => {
    const h = await harness();
    try {
        await h.store(yaml()); await h.event({occupancy: true});
        assert.equal(h.commands.length, 0);
        assert.equal((await h.activate()).status, 'ok');
        await h.event({occupancy: true});
        assert.equal(h.commands.length, 1);
        assert.equal(h.commands[0].topic, 'synthetic/target/set');
        assert.deepEqual(h.commands[0].data, {state: 'ON'});
        assert.equal(h.commands[0].options.clientOptions.retain, false);
        assert.equal(h.extension.status().executing, true);
    } finally { await h.close(); }
});

test('activation binds session, revision and checksum; disabled rules and missing devices never execute', async () => {
    const h = await harness();
    try {
        await h.store(yaml());
        for (const [extra, event, error] of [
            [{session: 'previous-session'}, {}, 'session_mismatch'],
            [{expected_revision: 0}, {}, 'revision_conflict'],
            [{checksum: 'f'.repeat(64)}, {}, 'checksum_mismatch'],
            [{}, {retain: true}, 'session_mismatch']
        ]) assert.equal((await h.activate(extra, event)).error.code, error);
        assert.equal(h.extension.status().executing, false);
        h.devices.delete(targetID);
        assert.equal((await h.activate()).error.code, 'unknown_entity');
        const disabled = rule(); disabled.enabled = false;
        await h.store(yaml([disabled], 2));
        assert.equal((await h.activate()).status, 'ok');
        await h.event({occupancy: true});
        assert.equal(h.commands.length, 0);
        assert.equal(h.extension.status().enabled_count, 0);
    } finally { await h.close(); }
});

test('only fresh matching typed properties trigger; cached and unrelated fields are ignored', async () => {
    const h = await harness();
    try {
        await h.store(yaml()); await h.activate();
        await h.event({temperature: 20}, {message: {occupancy: true}});
        await h.event({occupancy: true}, {stateChangeReason: 'publishCached'});
        await h.event({occupancy: 'true'});
        await h.event({occupancy: true}, {entity: h.group});
        assert.equal(h.commands.length, 0);
        await h.event({occupancy: true});
        await h.event({occupancy: true});
        assert.equal(h.commands.length, 1, 'feedback/cooldown prevents an immediate second run');
        await h.clock.advance(1000); await h.event({occupancy: true});
        assert.equal(h.commands.length, 2, 'a later fresh report is a new event');
    } finally { await h.close(); }
});

test('numeric comparisons and all conditions use observed values without coercion', async () => {
    const h = await harness();
    try {
        const r = rule();
        r.triggers[0] = {...r.triggers[0], property: 'temperature', operator: 'gte', value: 20};
        r.conditions = [{type: 'device_value', device: sourceID, property: 'enabled', operator: 'ne', value: false}];
        await h.store(yaml([r])); await h.activate();
        await h.event({temperature: 21});
        await h.event({temperature: '21', enabled: true});
        assert.equal(h.commands.length, 0, 'missing ne-value and a numeric string both fail closed');
        await h.event({temperature: 20});
        assert.equal(h.commands.length, 1);
        await h.clock.advance(1000); await h.event({temperature: 19});
        assert.equal(h.commands.length, 1);
    } finally { await h.close(); }
});

test('for_seconds measures continuous match, survives repeated matching reports and cancels on false', async () => {
    const h = await harness();
    try {
        const r = rule(); r.triggers[0].for_seconds = 5;
        await h.store(yaml([r])); await h.activate();
        await h.event({occupancy: true}); await h.clock.advance(3000);
        await h.event({occupancy: true}); await h.clock.advance(2000);
        assert.equal(h.commands.length, 1, 'repeated true must not restart the hold timer');
        await h.event({occupancy: true}); await h.clock.advance(5000);
        assert.equal(h.commands.length, 1, 'a continuous match runs only once');
        await h.event({occupancy: false}); await h.event({occupancy: true});
        await h.clock.advance(4000); await h.event({occupancy: false}); await h.clock.advance(2000);
        assert.equal(h.commands.length, 1, 'broken duration does not fire');
        await h.event({occupancy: true}); await h.clock.advance(5000);
        assert.equal(h.commands.length, 2);
    } finally { await h.close(); }
});

test('ordered delayed actions resolve current names and support groups; concurrent runs of one rule are dropped', async () => {
    const h = await harness();
    try {
        const r = rule();
        r.actions = [{...r.actions[0], delay_seconds: 2}, {type: 'set', target: {type: 'group', id: 7}, values: {state: 'OFF'}, delay_seconds: 3}];
        await h.store(yaml([r])); await h.activate(); await h.event({occupancy: true});
        await h.clock.advance(1000); await h.event({occupancy: true});
        h.devices.get(targetID).name = 'synthetic/renamed';
        await h.clock.advance(1000);
        assert.equal(h.commands.length, 1); assert.equal(h.commands[0].topic, 'synthetic/renamed/set');
        await h.clock.advance(2999); assert.equal(h.commands.length, 1);
        await h.clock.advance(1); assert.equal(h.commands.length, 2);
        assert.equal(h.commands[1].topic, 'synthetic/group/set');
    } finally { await h.close(); }
});

test('deactivate, changed storage and stop cancel every pending hold and delayed action', async () => {
    const h = await harness();
    try {
        const r = rule(); r.actions[0].delay_seconds = 5;
        await h.store(yaml([r])); await h.activate(); await h.event({occupancy: true});
        await h.deactivate(); await h.clock.advance(6000); assert.equal(h.commands.length, 0);
        await h.activate(); await h.event({occupancy: true});
        await h.store(yaml([r], 2)); await h.clock.advance(6000); assert.equal(h.commands.length, 0);
        assert.equal(h.extension.status().executing, false);
        await h.activate(); await h.event({occupancy: true});
        await h.extension.stop(); await h.clock.advance(6000); assert.equal(h.commands.length, 0);
        assert.equal(h.clock.timers.size, 0);
    } finally { await h.close(); }
});

test('unchanged stores and duplicate activation keep a pending hold; changed conditions cancel a delayed sequence', async () => {
    const h = await harness();
    try {
        const r = rule(); r.triggers[0].for_seconds = 2; r.actions[0].delay_seconds = 2;
        r.conditions = [{type: 'device_value', device: sourceID, property: 'allowed', operator: 'eq', value: true}];
        const text = yaml([r]);
        await h.store(text); await h.activate(); await h.event({occupancy: true, allowed: true});
        await h.clock.advance(1000); await h.activate();
        const response = await h.store(text);
        assert.equal(response.data.unchanged, true); assert.equal(response.data.executing, true);
        await h.clock.advance(1000); await h.event({allowed: false}); await h.clock.advance(2000);
        assert.equal(h.commands.length, 0);
        await h.event({occupancy: false}); await h.event({occupancy: true, allowed: true});
        await h.clock.advance(4000); assert.equal(h.commands.length, 1);
    } finally { await h.close(); }
});

test('time triggers use explicit zones, fire once per local day and do not replay DST repeated hours', async () => {
    const h = await harness();
    try {
        const r = rule(); r.triggers = [{type: 'time', at: '02:30:00', timezone: 'Europe/Berlin'}];
        await h.store(yaml([r])); await h.activate();
        h.clock.wall = Date.parse('2026-10-25T00:29:59Z'); await h.clock.advance(1000);
        assert.equal(h.commands.length, 1);
        h.clock.wall = Date.parse('2026-10-25T01:29:59Z'); await h.clock.advance(1000);
        assert.equal(h.commands.length, 1, 'repeated local hour must not run twice');
        h.clock.wall = Date.parse('2026-10-26T01:29:59Z'); await h.clock.advance(1000);
        assert.equal(h.commands.length, 2);
        await h.deactivate(); assert.equal(h.clock.timers.size, 0);
    } finally { await h.close(); }
});

test('overnight windows use weekday of the window start and exclude the end boundary', async () => {
    const h = await harness();
    try {
        const r = rule();
        r.conditions = [{type: 'time_window', after: '22:00:00', before: '06:00:00', timezone: 'UTC', weekdays: [1]}];
        await h.store(yaml([r])); await h.activate();
        h.clock.wall = Date.parse('2026-10-27T01:00:00Z'); await h.event({occupancy: true});
        assert.equal(h.commands.length, 1, 'Tuesday morning belongs to Monday night');
        await h.clock.advance(1000); h.clock.wall = Date.parse('2026-10-27T06:00:00Z'); await h.event({occupancy: true});
        assert.equal(h.commands.length, 1);
    } finally { await h.close(); }
});

test('action transport failures abort sequences without retry and diagnostics never expose payloads or exception text', async () => {
    const h = await harness();
    try {
        const r = rule(); r.actions[0].values = {state: 'synthetic-private-body'};
        r.actions.push({type: 'set', target: {type: 'group', id: 7}, values: {state: 'OFF'}});
        await h.store(yaml([r])); await h.activate(); h.sendFault = true;
        await h.event({occupancy: true});
        assert.equal(h.commands.length, 1);
        assert.equal(h.extension.status().runtime_error, 'action_failed');
        assert.equal(h.logs.some(line => line.includes('synthetic-private-body')), false);
        h.sendFault = false; h.logFault = true;
        await h.clock.advance(1000); await h.event({occupancy: true});
        assert.equal(h.commands.length, 3, 'broken debug does not suppress actions');
    } finally { await h.close(); }
});

test('activation refuses corrupted disk and restarts load stored rules without executing them', async () => {
    let h = await harness(); const directory = h.directory;
    try {
        await h.store(yaml()); await h.activate();
        await h.close(false); h = await harness(directory);
        assert.equal(h.extension.status().stored_revision, 1);
        assert.equal(h.extension.status().executing, false);
        await h.event({occupancy: true}); assert.equal(h.commands.length, 0);
        await writeFile(join(directory, 'automations.yaml'), 'broken: [');
        assert.equal((await h.activate()).error.code, 'storage_unavailable');
        assert.equal(h.extension.status().executing, false);
    } finally { await h.close(); }
});

test('an activation token is fresh, one-use and invalidated by deactivation; retained status never grants it', async () => {
    const h = await harness();
    try {
        await h.store(yaml());
        const activation = async () => ({session: h.session, expected_revision: 1, checksum: h.extension.status().checksum,
            activation_token: (await h.request('status')).data.activation_token});
        const request = await activation();
        assert.equal((await h.request('activate', request)).status, 'ok');
        await h.deactivate();
        assert.equal((await h.request('activate', request)).error.code, 'activation_expired');
        const expired = await activation(); await h.clock.advance(30000);
        assert.equal((await h.request('activate', expired)).error.code, 'activation_expired');
        assert.equal(h.extension.status().executing, false);
        assert.ok(h.messages.filter(message => message.topic === 'symcon/automations/status').every(message => !Object.hasOwn(message.data, 'activation_token')));
    } finally { await h.close(); }
});

test('unknown targets disappearing during delays abort safely and recovered backup cannot be activated', async () => {
    let h = await harness(); const directory = h.directory;
    try {
        const r = rule(); r.actions[0].delay_seconds = 1;
        await h.store(yaml([r])); await h.activate(); await h.event({occupancy: true});
        h.devices.delete(targetID); await h.clock.advance(1000);
        assert.equal(h.commands.length, 0); assert.equal(h.extension.status().runtime_error, 'unknown_entity');
        await h.store(yaml([], 2)); await h.close(false);
        await writeFile(join(directory, 'automations.yaml'), 'broken: [');
        h = await harness(directory);
        assert.equal(h.extension.status().storage, 'recovered_read_only');
        assert.equal((await h.activate()).error.code, 'storage_unavailable');
    } finally { await h.close(); }
});

test('global publication rate and concurrent rules are bounded; hung publication times out without a retry', async () => {
    const h = await harness();
    try {
        const r = rule();
        r.actions = Array.from({length: 16}, () => ({type: 'set', target: {type: 'device', id: targetID}, values: {state: 'ON'}}));
        const second = structuredClone(r); second.id = 'second_rule';
        await h.store(yaml([r, second])); await h.activate();
        await h.event({occupancy: true}); await Promise.all([...h.extension.runtime.tasks]);
        assert.equal(h.commands.length, 20, 'commands beyond the global rate are dropped');
        assert.equal(h.extension.status().runtime_error, 'rate_limited');
        await h.store(yaml([rule()], 2)); await h.activate();
        await h.clock.advance(1000);
        h.extension.runtime.mqtt = {publish: () => new Promise(() => {})};
        await h.event({occupancy: true}); await h.clock.advance(5000);
        assert.equal(h.extension.status().runtime_error, 'action_timeout');
        assert.equal(h.extension.runtime.tasks.size, 0);
        assert.equal(h.clock.timers.size, 0);
    } finally { await h.close(); }
});

test('all six comparisons are strict and OR triggers run with AND conditions', async () => {
    for (const [operator, actual, expected] of [['eq', 2, 2], ['ne', 3, 2], ['gt', 3, 2], ['gte', 2, 2], ['lt', 1, 2], ['lte', 2, 2]]) {
        const h = await harness();
        try {
            const r = rule();
            r.triggers.push({type: 'device', device: sourceID, property: 'temperature', operator, value: expected});
            r.conditions = [{type: 'device_value', device: sourceID, property: 'allowed', operator: 'eq', value: true},
                {type: 'device_value', device: sourceID, property: 'blocked', operator: 'eq', value: false}];
            await h.store(yaml([r])); await h.activate();
            await h.event({temperature: actual, allowed: true, blocked: true}); assert.equal(h.commands.length, 0);
            await h.event({temperature: actual, allowed: true, blocked: false}); assert.equal(h.commands.length, 1, operator);
        } finally { await h.close(); }
    }
});

test('time windows include start, exclude end and missed wall-clock triggers are not caught up', async () => {
    const h = await harness();
    try {
        const r = rule();
        r.conditions = [{type: 'time_window', after: '12:00:00', before: '13:00:00', timezone: 'UTC'}];
        await h.store(yaml([r])); await h.activate();
        await h.event({occupancy: true}); assert.equal(h.commands.length, 1);
        await h.clock.advance(1000); h.clock.wall = Date.parse('2026-10-26T13:00:00Z');
        await h.event({occupancy: true}); assert.equal(h.commands.length, 1);
        r.conditions = []; r.triggers = [{type: 'time', at: '14:00:00', timezone: 'UTC'}];
        await h.store(yaml([r], 2)); await h.activate();
        h.clock.wall = Date.parse('2026-10-26T14:00:01Z'); await h.clock.advance(1000);
        assert.equal(h.commands.length, 1);
    } finally { await h.close(); }
});

test('parallel rule count is bounded and deactivation cancels held triggers as well as action waits', async () => {
    const h = await harness();
    try {
        const rules = Array.from({length: 25}, (_, index) => {
            const r = rule(); r.id = 'rule_' + index; r.actions[0].delay_seconds = 10; return r;
        });
        await h.store(yaml(rules)); await h.activate(); await h.event({occupancy: true});
        assert.equal(h.extension.runtime.tasks.size, 16);
        await h.deactivate(); await flush();
        assert.equal(h.extension.runtime.tasks.size, 0);
        const held = rule(); held.triggers[0].for_seconds = 10;
        await h.store(yaml([held], 2)); await h.activate(); await h.event({occupancy: true});
        await h.deactivate(); await h.clock.advance(10000);
        assert.equal(h.commands.length, 0); assert.equal(h.clock.timers.size, 0);
    } finally { await h.close(); }
});

test('stop during activation disk verification cannot arm a stopped extension', async () => {
    const h = await harness();
    try {
        await h.store(yaml());
        const status = await h.request('status');
        let unblock;
        h.extension.store.verifyDisk = () => new Promise(resolve => { unblock = resolve; });
        const activation = h.request('activate', {session: h.session, activation_token: status.data.activation_token,
            expected_revision: 1, checksum: h.extension.status().checksum});
        await flush();
        const stopped = h.extension.stop(); unblock();
        assert.equal((await activation).error.code, 'not_ready');
        await stopped;
        assert.equal(h.extension.status().executing, false);
        assert.equal(h.clock.timers.size, 0);
    } finally { await h.close(); }
});

test('diagnostic start/end pairs remain balanced for rejected activation and failed action sequences', async () => {
    const h = await harness();
    try {
        await h.store(yaml());
        await h.activate({checksum: 'a'.repeat(64)});
        await h.activate(); h.sendFault = true; await h.event({occupancy: true});
        await h.extension.stop();
        const starts = h.logs.filter(line => line.includes(' | START | '));
        const ends = h.logs.filter(line => line.includes(' | ENDE | '));
        assert.ok(starts.length > 5);
        assert.equal(starts.length, ends.length);
        assert.ok(h.logs.some(line => line.includes('checksum_mismatch')));
        assert.ok(h.logs.some(line => line.includes('action_failed')));
        assert.equal(h.logs.some(line => line.includes(h.session)), false);
        assert.equal(h.logs.some(line => line.includes('synthetic-private-body')), false);
    } finally { await h.close(); }
});
