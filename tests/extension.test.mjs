import assert from 'node:assert/strict';
import test from 'node:test';
import * as fs from 'node:fs/promises';
import {readFile, writeFile, readdir, mkdtemp, mkdir, symlink, copyFile, rm} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
import {fileURLToPath, pathToFileURL} from 'node:url';
import Extension, {validateYaml, LIMITS, buildEditorCatalog, buildEditorGroups} from '../libs/z2m/z2m-automations.mjs';

const emptyYaml = revision => `schema_version: 1\nrevision: ${revision}\nautomations: []\n`;
const fixtureYaml = await readFile(new URL('../libs/z2m/examples/automations.yaml', import.meta.url), 'utf8');

test('click editor reserves basic state choices for later devices before filling special options', () => {
    const devices = Array.from({length: 12}, (_, i) => ({ieeeAddr: '0x' + (i + 1).toString(16).padStart(16, '0'), name: 'Synthetic rich device', isDevice: () => true,
        exposes: () => Array.from({length: 8}, (_, n) => ({property: 'special_' + n, type: 'enum', access: 7, values: Array.from({length: 32}, (_, v) => 'option_' + v)}))}));
    devices.push({ieeeAddr: '0x00000000000000ff', name: 'Synthetic later light', isDevice: () => true,
        exposes: () => [{type: 'binary', property: 'state', access: 7, value_on: 'ON', value_off: 'OFF'}]});
    const catalog = buildEditorCatalog({devicesIterator: () => devices});
    const light = catalog.find(device => device.id === '0x00000000000000ff');
    assert.deepEqual(light?.actions.map(choice => choice.value), ['ON', 'OFF']);
    assert.deepEqual(light?.triggers.map(choice => choice.value), ['ON', 'OFF']);
    assert.ok(catalog.reduce((sum, device) => sum + device.actions.length + device.triggers.length + device.numeric.length, 0) <= 1024);
    let exposed = 0;
    const lights = Array.from({length: 257}, (_, i) => ({ieeeAddr: '0x' + (i + 1).toString(16).padStart(16, '0'), name: 'Synthetic light', isDevice: () => true,
        exposes: () => { exposed++; return [{type: 'binary', property: 'state', access: 7, value_on: 'ON', value_off: 'OFF'}]; }}));
    const bounded = buildEditorCatalog({devicesIterator: () => lights});
    assert.equal(bounded.length, 256);
    assert.equal(exposed, 256, 'Exposes are read once per admitted device');
    assert.equal(bounded.reduce((sum, device) => sum + device.actions.length + device.triggers.length, 0), 1024);
});

test('click editor reserves individual switch channels before special options exhaust the budget', () => {
    const devices = Array.from({length: 12}, (_, i) => ({ieeeAddr: '0x' + (i + 1).toString(16).padStart(16, '0'), name: 'Synthetic rich device', isDevice: () => true,
        exposes: () => Array.from({length: 8}, (_, n) => ({property: 'special_' + n, type: 'enum', access: 7, values: Array.from({length: 32}, (_, v) => 'option_' + v)}))}));
    devices.push({ieeeAddr: '0x00000000000000ff', name: 'Synthetic three channel switch', isDevice: () => true,
        exposes: () => [1, 2, 3].map(n => ({type: 'switch', endpoint: 'l' + n, features: [
            {name: 'state', property: 'state_l' + n, endpoint: 'l' + n, type: 'binary', access: 7, value_on: 'ON', value_off: 'OFF'}]}))});
    const catalog = buildEditorCatalog({devicesIterator: () => devices});
    const device = catalog.find(item => item.id === '0x00000000000000ff');
    const expected = [1, 2, 3].flatMap(n => [{property: 'state_l' + n, value: 'ON'}, {property: 'state_l' + n, value: 'OFF'}]);
    assert.deepEqual(device?.triggers, expected);
    assert.deepEqual(device?.actions, expected);
    assert.ok(!device.triggers.some(item => item.property === 'state'), 'Explicit channel properties are preserved without synthesizing a general state');
    assert.ok(catalog.reduce((sum, item) => sum + item.triggers.length + item.actions.length + item.numeric.length, 0) <= 1024);
});

test('click editor reads bounded discrete Exposes and ignores unsupported values and raw definitions', () => {
    const catalog = buildEditorCatalog({devicesIterator: () => [{ieeeAddr: '0x0000000000000001', name: 'Synthetic button', isDevice: () => true,
        exposes: () => [{features: [{property: 'action', type: 'enum', access: 1, values: ['single', 'double']},
            {property: 'state', type: 'binary', access: 7, value_on: true, value_off: false},
            {property: 'temperature', type: 'numeric', access: 1, value_min: -40, value_max: 80},
            {property: '__proto__', type: 'enum', access: 7, values: ['forbidden']},
            {property: 'bad', type: 'enum', access: 7, values: [{private: 'raw-definition'}]}]}]}]});
    assert.equal(catalog.length, 1);
    assert.deepEqual(catalog[0].triggers.map(item => item.property), ['state', 'state', 'action', 'action']);
    assert.deepEqual(catalog[0].actions.map(item => item.value), [true, false]);
    assert.equal(JSON.stringify(catalog).includes('raw-definition'), false);
    let seen = 0;
    const many = {devicesIterator: function* () { for (let i = 0; i < 1000; i++) { seen++; yield {isDevice: () => false}; } }};
    assert.deepEqual(buildEditorCatalog(many), []);
    assert.ok(seen <= 257);
});

test('click editor exposes bounded readable numeric properties without copying raw metadata', () => {
    const features = [{property: 'temperature', type: 'numeric', access: 1, unit: '°C', private: 'raw-secret'},
        {property: 'write_only', type: 'numeric', access: 2}, {property: '__proto__', type: 'numeric', access: 1},
        ...Array.from({length: 100}, (_, i) => ({property: 'value_' + i, type: 'numeric', access: 1, unit: 'x'.repeat(100)}))];
    const [device] = buildEditorCatalog({devicesIterator: () => [{ieeeAddr: '0x0000000000000003', name: 'Synthetic sensor', isDevice: () => true, exposes: () => features}]});
    assert.equal(device.numeric.length, 64);
    assert.deepEqual(device.numeric[0], {property: 'temperature', unit: '°C'});
    assert.equal(device.triggers.length, 0);
    assert.equal(device.actions.length, 0);
    assert.ok(device.numeric.every(item => item.unit.length <= 24 && item.property !== 'write_only' && item.property !== '__proto__'));
    assert.equal(JSON.stringify(device).includes('raw-secret'), false);
});

test('generic property metadata preserves endpoints, access and numeric constraints', () => {
    const [device] = buildEditorCatalog({devicesIterator: () => [{ieeeAddr: '0x0000000000000005', name: 'Synthetic valve', isDevice: () => true,
        exposes: () => [{endpoint: 'upper', features: [
            {property: 'valve_upper', label: 'Valve position', type: 'numeric', access: 3, value_min: 10, value_max: 90, value_step: 5},
            {property: 'mode_upper', label: 'Mode', type: 'enum', access: 2, values: ['auto', 'manual']},
            {property: 'temperature_upper', type: 'numeric', access: 1},
        ]}]}]});
    const properties = Object.fromEntries(device.properties.map(item => [item.property, item]));
    assert.deepEqual(properties.valve_upper, {property: 'valve_upper', label: 'Valve position', type: 'numeric', access: 3,
        endpoint: 'upper', values: [], minimum: 10, maximum: 90, step: 5});
    assert.equal(properties.mode_upper.access, 2);
    assert.deepEqual(properties.mode_upper.values, ['auto', 'manual']);
    assert.equal(properties.temperature_upper.access, 1);
    assert.ok(!device.triggers.some(item => item.property === 'mode_upper'));
});

async function harness(options = {}) {
    const directory = options.directory ?? await mkdtemp(join(tmpdir(), 'z2ma-test-'));
    const messages = [];
    const logs = [];
    let handler;
    const mqtt = {publish: async (topic, payload, publishOptions) => {
        if (options.failPublish?.()) throw new Error('synthetic mqtt fault');
        messages.push({topic, payload: JSON.parse(payload), options: publishOptions});
    }};
    const eventBus = {onMQTTMessage: (_owner, listener) => { handler = listener; }, removeListeners: () => { handler = undefined; }};
    const settings = {get: () => ({mqtt: {base_topic: 'synthetic/z2m'}})};
    const logger = {error: text => logs.push(text), warning: text => logs.push(text)};
    const extension = new Extension(null, mqtt, null, null, eventBus, null, null, null, settings, logger, {directory, io: options.io});
    await extension.start();
    const session = messages.at(-1)?.payload.session ?? extension.status().session;
    let sequence = 0;
    const request = async (operation, data = {}, event = {}) => {
        const transaction = 'synthetic-' + ++sequence;
        await handler({topic: 'synthetic/z2m/symcon/automations/request/' + operation,
            message: JSON.stringify({protocol_version: 1, transaction, ...data}), ...event});
        return messages.findLast(message => message.payload.transaction === transaction)?.payload;
    };
    const store = (yaml, expected_revision, extra = {}) => request('store', {session, expected_revision, yaml, ...extra});
    return {directory, messages, logs, extension, session, request, store,
        raw: event => handler(event),
        close: async (remove = true) => {
            await extension.stop();
            if (remove) await rm(directory, {recursive: true, force: true});
        }};
}

test('schema accepts the complete synthetic example and an empty rule list', () => {
    assert.equal(validateYaml(fixtureYaml).document.automations.length, 2);
    assert.equal(validateYaml(emptyYaml(1)).document.revision, 1);
});

test('strict YAML and schema validation reject malformed or ambiguous documents', () => {
    const source = validateYaml(fixtureYaml).document;
    const invalidDocuments = [
        {...source, schema_version: 2}, {...source, revision: 0}, {...source, revision: 1.5},
        {...source, revision: 9007199254740992}, {...source, unknown: true},
        {...source, automations: [...source.automations, source.automations[0]]}
    ];
    for (const mutation of [
        item => { delete item.enabled; }, item => { item.enabled = 'false'; },
        item => { item.triggers = []; }, item => { item.actions = []; },
        item => { item.triggers[0].device = 'friendly-name'; },
        item => { item.triggers[0].operator = 'execute'; },
        item => { item.triggers[0].operator = 'gt'; item.triggers[0].value = 'text'; },
        item => { item.conditions[0].timezone = 'NoSuch/Timezone'; },
        item => { item.conditions[0].before = item.conditions[0].after; },
        item => { item.conditions[0].weekdays = [1, 1]; },
        item => { item.actions[0].target = {type: 'group', id: '7'}; },
        item => { item.actions[0].values = {}; },
        item => { item.actions[0].delay_seconds = -1; }
    ]) {
        const document = structuredClone(source);
        mutation(document.automations[0]);
        invalidDocuments.push(document);
    }
    for (const document of invalidDocuments) assert.throws(() => validateYaml(JSON.stringify(document)));
    for (const yaml of [
        '', '[]', 'null', 'schema_version: [', emptyYaml(1) + 'revision: 2\n',
        emptyYaml(1) + '---\n' + emptyYaml(2),
        'schema_version: !!int 1\nrevision: 1\nautomations: []\n',
        'schema_version: 1\nrevision: 1\nautomations: &rules []\n',
        'schema_version: 1\nrevision: 1\nautomations: [*rules]\n',
        '{__proto__: {injected: true}}', '{constructor: {prototype: {injected: true}}}',
        '{<<: {revision: 1}}', '{[1,2]: x}',
        'x: ' + '['.repeat(20) + '0' + ']'.repeat(20),
        '#'.repeat(LIMITS.yamlBytes + 1), 'schema_version: 1\nrevision: 1\nautomations: []\n#\ud800'
    ]) assert.throws(() => validateYaml(yaml));
    assert.equal({}.injected, undefined);
});

test('validate is read-only and malformed requests preserve persisted YAML', async () => {
    const h = await harness();
    try {
        assert.equal((await h.request('validate', {yaml: fixtureYaml})).data.valid, true);
        assert.deepEqual(await readdir(h.directory), []);
        await h.store(emptyYaml(1), 0);
        for (const [yaml, expected] of [['schema_version: [', 'invalid_yaml'], [emptyYaml(1).replace('schema_version: 1', 'schema_version: 9'), 'invalid_schema']]) {
            const response = await h.store(yaml, 1);
            assert.equal(response.error.code, expected);
            assert.equal(await readFile(join(h.directory, 'automations.yaml'), 'utf8'), emptyYaml(1));
        }
        const response = await h.request('store', {session: h.session, expected_revision: 1, yaml: emptyYaml(2), filename: '../escape.yaml'});
        assert.equal(response.error.code, 'invalid_request');
        assert.equal(response.transaction, 'synthetic-5');
        assert.equal((await h.request('get')).data.yaml, emptyYaml(1));
        assert.ok(h.messages.every(message => message.topic.startsWith('symcon/automations/')));
        assert.ok(h.messages.filter(message => message.topic.startsWith('symcon/automations/response/')).every(message => message.options.clientOptions.retain === false));
    } finally { await h.close(); }
});

test('revision conflicts, duplicates and concurrent writes are deterministic', async () => {
    const h = await harness();
    try {
        assert.equal((await h.store(emptyYaml(1), 0)).data.unchanged, false);
        assert.equal((await h.store(emptyYaml(1), 0)).data.unchanged, true);
        assert.equal((await h.store(emptyYaml(1) + '# changed\n', 1)).error.code, 'revision_reused');
        assert.equal((await h.store(emptyYaml(3), 1)).error.code, 'revision_reused');
        assert.equal((await h.store(emptyYaml(2), 0)).error.code, 'revision_conflict');
        const responses = await Promise.all([h.store(emptyYaml(2), 1), h.store(emptyYaml(2) + '# conflict\n', 1)]);
        assert.equal(responses[0].status, 'ok');
        assert.equal(responses[1].error.code, 'revision_conflict');
        assert.equal(await readFile(join(h.directory, 'automations.yaml'), 'utf8'), emptyYaml(2));
        assert.equal(await readFile(join(h.directory, 'automations.previous.yaml'), 'utf8'), emptyYaml(1));
        assert.ok((await readdir(h.directory)).every(name => !name.endsWith('.tmp')));
    } finally { await h.close(); }
});

test('session nonce rejects retained and previous-session writes', async () => {
    let h = await harness();
    try {
        const priorSession = h.session;
        assert.equal((await h.store(emptyYaml(1), 0, {session: 'old'})).error.code, 'session_mismatch');
        const response = await h.request('store', {session: h.session, expected_revision: 0, yaml: emptyYaml(1)}, {retain: true});
        assert.equal(response.error.code, 'session_mismatch');
        await h.close(false);
        h = await harness({directory: h.directory});
        assert.notEqual(h.session, priorSession);
        assert.equal((await h.store(emptyYaml(1), 0, {session: priorSession})).error.code, 'session_mismatch');
        assert.equal((await h.store(emptyYaml(1), 0)).status, 'ok');
    } finally { await h.close(); }
});

test('disk write failures keep the previous primary intact and clean temporary files', async () => {
    for (const stage of ['open', 'write', 'sync', 'close', 'backup-rename', 'primary-rename']) {
        let failing = false;
        const error = () => Object.assign(new Error('synthetic-private-disk-error'), {code: 'EIO'});
        const io = {...fs,
            open: async (path, flags, mode) => {
                if (failing && stage === 'open' && flags === 'wx') throw error();
                const handle = await fs.open(path, flags, mode);
                if (flags !== 'wx') return handle;
                return {
                    writeFile: async (...args) => { if (failing && stage === 'write') throw error(); return handle.writeFile(...args); },
                    sync: async () => { if (failing && stage === 'sync') throw error(); return handle.sync(); },
                    close: async () => { await handle.close(); if (failing && stage === 'close') throw error(); }
                };
            },
            rename: async (from, to) => {
                if (failing && ((stage === 'backup-rename' && to.endsWith('automations.previous.yaml'))
                    || (stage === 'primary-rename' && to.endsWith('automations.yaml')))) throw error();
                return fs.rename(from, to);
            }
        };
        const h = await harness({io});
        try {
            await h.store(emptyYaml(1), 0);
            failing = true;
            const response = await h.store(emptyYaml(2), 1);
            assert.equal(response.error.code, 'storage_error', stage);
            assert.ok(!JSON.stringify(response).includes('synthetic-private-disk-error'));
            assert.equal(await readFile(join(h.directory, 'automations.yaml'), 'utf8'), emptyYaml(1), stage);
            assert.equal(h.extension.status().stored_revision, 1);
            assert.ok((await readdir(h.directory)).every(name => !name.endsWith('.tmp')));
            failing = false;
            assert.equal((await h.store(emptyYaml(2), 1)).status, 'ok');
        } finally { await h.close(); }
    }
});

test('startup recovers backup read-only, preserving damaged primary and revision history', async () => {
    let h = await harness();
    try {
        await h.store(emptyYaml(1), 0);
        await h.store(emptyYaml(2), 1);
        await h.close(false);
        await writeFile(join(h.directory, 'automations.yaml'), 'damaged: [');
        h = await harness({directory: h.directory});
        assert.equal(h.extension.status().storage, 'recovered_read_only');
        assert.equal(h.extension.status().stored_revision, 1);
        assert.equal((await h.request('get')).data.yaml, emptyYaml(1));
        assert.equal((await h.store(emptyYaml(2), 1)).error.code, 'storage_unavailable');
        assert.equal(await readFile(join(h.directory, 'automations.yaml'), 'utf8'), 'damaged: [');
    } finally { await h.close(); }
});

test('external disk edits are detected even for idempotent replays', async () => {
    const h = await harness();
    try {
        await h.store(emptyYaml(1), 0);
        await writeFile(join(h.directory, 'automations.yaml'), emptyYaml(9));
        assert.equal((await h.store(emptyYaml(1), 0)).error.code, 'storage_unavailable');
        assert.equal(await readFile(join(h.directory, 'automations.yaml'), 'utf8'), emptyYaml(9));
    } finally { await h.close(); }
});

test('invalid JSON, unknown topics, request bounds and MQTT failures stay contained', async () => {
    let failed = false;
    const h = await harness({failPublish: () => failed});
    try {
        await h.raw({topic: 'other/symcon/automations/request/store', message: '{}'});
        await h.raw({topic: 'synthetic/z2m/symcon/automations/request/delete', message: '{}'});
        assert.equal(h.messages.length, 1);
        for (const message of ['invalid-secret: [', '[]', '{}', 'x'.repeat(LIMITS.requestBytes + 1)]) {
            await h.raw({topic: 'synthetic/z2m/symcon/automations/request/store', message});
            assert.equal(h.messages.at(-1).payload.error.code, 'invalid_request');
            assert.ok(!JSON.stringify(h.messages.at(-1)).includes('invalid-secret'));
        }
        failed = true;
        await h.store(emptyYaml(1), 0);
        assert.equal(await readFile(join(h.directory, 'automations.yaml'), 'utf8'), emptyYaml(1));
        failed = false;
        assert.equal((await h.store(emptyYaml(1), 0)).data.unchanged, true);
        assert.ok(h.logs.every(text => !text.includes('invalid-secret')));
    } finally { await h.close(); }
});

test('request queue is bounded while a real disk operation is pending', async () => {
    let block = false;
    let release;
    let entered;
    const blocked = new Promise(resolve => { entered = resolve; });
    const gate = new Promise(resolve => { release = resolve; });
    const io = {...fs, open: async (...args) => {
        if (block && args[1] === 'wx') {
            entered();
            await gate;
        }
        return fs.open(...args);
    }};
    const h = await harness({io});
    try {
        await h.store(emptyYaml(1), 0);
        block = true;
        const saving = h.store(emptyYaml(2), 1);
        await blocked;
        const queued = Array.from({length: LIMITS.queuedRequests + 2}, () => h.request('status'));
        assert.ok(h.extension.pending <= LIMITS.queuedRequests);
        release();
        const responses = await Promise.all(queued);
        assert.ok(responses.some(response => response.error?.code === 'busy'));
        assert.equal((await saving).status, 'ok');
        assert.equal(h.extension.pending, 0);
    } finally { release(); await h.close(); }
});

test('invalid startup files block writes instead of resetting revision to zero', async () => {
    const directory = await mkdtemp(join(tmpdir(), 'z2ma-test-'));
    await writeFile(join(directory, 'automations.yaml'), 'x'.repeat(LIMITS.yamlBytes + 1));
    const h = await harness({directory});
    try {
        assert.equal(h.extension.status().storage, 'error');
        assert.equal((await h.store(emptyYaml(1), 0)).error.code, 'storage_unavailable');
        assert.equal((await fs.stat(join(directory, 'automations.yaml'))).size, LIMITS.yamlBytes + 1);
    } finally { await h.close(); }
});

test('deployed single-file artifact derives persistent storage from its actual location', async () => {
    const root = await mkdtemp(join(tmpdir(), 'z2ma-deployment-'));
    const extensionDirectory = join(root, 'data', 'external_extensions');
    const messages = [];
    const mqtt = {publish: async (topic, payload) => messages.push({topic, payload: JSON.parse(payload)})};
    let handler;
    const eventBus = {onMQTTMessage: (_owner, listener) => { handler = listener; }, removeListeners: () => { handler = undefined; }};
    try {
        await mkdir(extensionDirectory, {recursive: true});
        await symlink(fileURLToPath(new URL('../node_modules', import.meta.url)), join(extensionDirectory, 'node_modules'), 'junction');
        const deployedFile = join(extensionDirectory, '.tmp-synthetic-import.mjs');
        await copyFile(new URL('../libs/z2m/z2m-automations.mjs', import.meta.url), deployedFile);
        const DeployedExtension = (await import(pathToFileURL(deployedFile).href)).default;
        const extension = new DeployedExtension(null, mqtt, null, null, eventBus, null, null, null,
            {get: () => ({mqtt: {base_topic: 'synthetic/deployed'}})}, {error: () => {}});
        await extension.start();
        const session = messages.at(-1).payload.session;
        await handler({topic: 'synthetic/deployed/symcon/automations/request/store', message: JSON.stringify({
            protocol_version: 1, transaction: 'deployed-1', session, expected_revision: 0, yaml: emptyYaml(1)
        })});
        assert.equal(await readFile(join(root, 'data', 'z2m-automations', 'automations.yaml'), 'utf8'), emptyYaml(1));
        await extension.stop();
        assert.equal(handler, undefined);
        assert.equal(messages.at(-1).payload.state, 'stopped');
    } finally { await rm(root, {recursive: true, force: true}); }
});

test('MQTT store validates YAML, persists it and reloads it after restart', async () => {
    const directory = await mkdtemp(join(tmpdir(), 'z2ma-test-'));
    const messages = [];
    const mqtt = {publish: async (topic, payload, options) => messages.push({topic, payload: JSON.parse(payload), options})};
    let handler;
    const eventBus = {onMQTTMessage: (_owner, listener) => { handler = listener; }, removeListeners: () => { handler = undefined; }};
    const settings = {get: () => ({mqtt: {base_topic: 'synthetic/z2m'}})};
    const logger = {error: () => {}, warning: () => {}};
    const make = () => new Extension(null, mqtt, null, null, eventBus, null, null, null, settings, logger, {directory});
    const yaml = 'schema_version: 1\nrevision: 1\nautomations: []\n';
    try {
        const extension = make();
        await extension.start();
        const session = messages.at(-1).payload.session;
        await handler({topic: 'synthetic/z2m/symcon/automations/request/store', message: JSON.stringify({
            protocol_version: 1, transaction: 'test-1', session, expected_revision: 0, yaml
        })});
        const response = messages.findLast(message => message.topic.endsWith('/response/store')).payload;
        assert.equal(response.status, 'ok');
        assert.equal(response.data.stored_revision, 1);
        assert.equal(response.data.executing, false);
        assert.equal(await readFile(join(directory, 'automations.yaml'), 'utf8'), yaml);
        await extension.stop();
        const restarted = make();
        await restarted.start();
        assert.equal(messages.at(-1).payload.stored_revision, 1);
        assert.notEqual(messages.at(-1).payload.session, session);
        await restarted.stop();
    } finally {
        await rm(directory, {recursive: true, force: true});
    }
});


test('editor group catalog contains bounded stable identities without raw member data', () => {
    const group = {ID: 7, name: 'Synthetic group', private: 'raw-secret'};
    const groups = buildEditorGroups({groupsIterator: () => [group, group, {ID: 0, name: 'bad'}, {ID: 8, name: 'bad#topic'}].values()});
    assert.deepEqual(groups, [{id: 7, name: 'Synthetic group'}]);
    assert.deepEqual(buildEditorGroups({}), []);
    let visited = 0;
    const many = {groupsIterator: function* () { for (let id = 1; id < 1000; id++) { visited++; yield {ID: id, name: 'Synthetic group'}; } }};
    assert.equal(buildEditorGroups(many).length, 256);
    assert.ok(visited <= 257);
});


test('schema keeps fixed set and cache-based toggle actions distinct', () => {
    const document = {schema_version: 1, revision: 1, automations: [{id: 'synthetic_import', name: 'Synthetic import', enabled: false,
        triggers: [{type: 'device', device: '0x0000000000000001', property: 'action', operator: 'eq', value: 'single'}],
        conditions: [], actions: [{type: 'toggle', target: {type: 'group', id: 7}, property: 'state'}]}]};
    assert.equal(validateYaml(JSON.stringify(document)).document.automations[0].actions[0].type, 'toggle');
    for (const action of [{type: 'toggle', target: {type: 'group', id: 7}, property: 'brightness'},
        {type: 'toggle', target: {type: 'group', id: 7}, property: 'state', values: {state: 'ON'}},
        {type: 'set', target: {type: 'group', id: 7}, property: 'state'},
        {type: 'toggle', target: {type: 'group', id: 7}}]) {
        document.automations[0].actions = [action];
        assert.throws(() => validateYaml(JSON.stringify(document)), error => error.code === 'invalid_schema');
    }
});
