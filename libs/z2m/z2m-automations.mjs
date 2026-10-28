// Generated from src/extension.mjs, src/runtime.mjs and automations.schema.json. Do not edit.
import {createHash, randomUUID} from 'node:crypto';
import * as fs from 'node:fs/promises';
import {join} from 'node:path';
import {fileURLToPath} from 'node:url';
import {TextDecoder} from 'node:util';
import Ajv from 'ajv';
import {parseEvents, constructFromEvents, JSON_SCHEMA, mapTag, EVENT_ID} from 'js-yaml';
const AUTOMATIONS_SCHEMA = {"$schema":"http://json-schema.org/draft-07/schema#","$id":"urn:burki24:z2m-automations:schema:1","title":"Z2MAutomations configuration v1","type":"object","additionalProperties":false,"properties":{"schema_version":{"const":1},"revision":{"type":"integer","minimum":1,"maximum":9007199254740991},"automations":{"type":"array","maxItems":100,"items":{"$ref":"#/definitions/automation"}}},"required":["schema_version","revision","automations"],"definitions":{"device":{"type":"string","pattern":"^0x[0-9a-f]{16}$"},"jsonValue":{"anyOf":[{"type":["string","number","boolean","null"],"maxLength":256},{"type":"array","maxItems":32,"items":{"$ref":"#/definitions/jsonValue"}},{"type":"object","maxProperties":32,"propertyNames":{"type":"string","pattern":"^[A-Za-z][A-Za-z0-9_]{0,63}$","not":{"enum":["__proto__","constructor","prototype"]}},"additionalProperties":{"$ref":"#/definitions/jsonValue"}}]},"deviceTrigger":{"type":"object","additionalProperties":false,"properties":{"type":{"const":"device"},"device":{"$ref":"#/definitions/device"},"property":{"type":"string","pattern":"^[A-Za-z][A-Za-z0-9_]{0,63}$","not":{"enum":["__proto__","constructor","prototype"]}},"operator":{"enum":["eq","ne","gt","gte","lt","lte"]},"value":{"type":["string","number","boolean","null"],"maxLength":256},"for_seconds":{"type":"integer","minimum":0,"maximum":86400}},"required":["type","device","property","operator","value"],"allOf":[{"if":{"properties":{"operator":{"enum":["gt","gte","lt","lte"]}}},"then":{"properties":{"value":{"type":"number"}}}}]},"timeTrigger":{"type":"object","additionalProperties":false,"properties":{"type":{"const":"time"},"at":{"type":"string","pattern":"^([01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$"},"timezone":{"type":"string","minLength":1,"maxLength":64}},"required":["type","at","timezone"]},"deviceCondition":{"type":"object","additionalProperties":false,"properties":{"type":{"const":"device_value"},"device":{"$ref":"#/definitions/device"},"property":{"type":"string","pattern":"^[A-Za-z][A-Za-z0-9_]{0,63}$","not":{"enum":["__proto__","constructor","prototype"]}},"operator":{"enum":["eq","ne","gt","gte","lt","lte"]},"value":{"type":["string","number","boolean","null"],"maxLength":256}},"required":["type","device","property","operator","value"],"allOf":[{"if":{"properties":{"operator":{"enum":["gt","gte","lt","lte"]}}},"then":{"properties":{"value":{"type":"number"}}}}]},"timeCondition":{"type":"object","additionalProperties":false,"properties":{"type":{"const":"time_window"},"after":{"type":"string","pattern":"^([01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$"},"before":{"type":"string","pattern":"^([01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$"},"timezone":{"type":"string","minLength":1,"maxLength":64},"weekdays":{"type":"array","minItems":1,"maxItems":7,"uniqueItems":true,"items":{"type":"integer","minimum":1,"maximum":7}}},"required":["type","after","before","timezone"]},"target":{"oneOf":[{"type":"object","additionalProperties":false,"properties":{"type":{"const":"device"},"id":{"$ref":"#/definitions/device"}},"required":["type","id"]},{"type":"object","additionalProperties":false,"properties":{"type":{"const":"group"},"id":{"type":"integer","minimum":1,"maximum":65535}},"required":["type","id"]}]},"action":{"type":"object","additionalProperties":false,"properties":{"type":{"const":"set"},"target":{"$ref":"#/definitions/target"},"values":{"type":"object","minProperties":1,"maxProperties":32,"propertyNames":{"type":"string","pattern":"^[A-Za-z][A-Za-z0-9_]{0,63}$","not":{"enum":["__proto__","constructor","prototype"]}},"additionalProperties":{"$ref":"#/definitions/jsonValue"}},"delay_seconds":{"type":"integer","minimum":0,"maximum":86400}},"required":["type","target","values"]},"automation":{"type":"object","additionalProperties":false,"properties":{"id":{"type":"string","pattern":"^[a-z][a-z0-9_-]{0,63}$"},"name":{"type":"string","minLength":1,"maxLength":120},"enabled":{"type":"boolean"},"triggers":{"type":"array","minItems":1,"maxItems":8,"items":{"oneOf":[{"$ref":"#/definitions/deviceTrigger"},{"$ref":"#/definitions/timeTrigger"}]}},"conditions":{"type":"array","maxItems":16,"items":{"oneOf":[{"$ref":"#/definitions/deviceCondition"},{"$ref":"#/definitions/timeCondition"}]}},"actions":{"type":"array","minItems":1,"maxItems":16,"items":{"$ref":"#/definitions/action"}}},"required":["id","name","enabled","triggers","conditions","actions"]}}};
// Library-local execution policy. No foreign automation implementation is bundled.
export const RUNTIME_LIMITS = Object.freeze({concurrentRules: 16, commandsPerSecond: 20, ruleCooldownMs: 1000, publishTimeoutMs: 5000});
const object = value => value !== null && typeof value === 'object' && !Array.isArray(value);
const scalar = value => value === null || ['string', 'boolean'].includes(typeof value) || (typeof value === 'number' && Number.isFinite(value));
const nativeClock = {now: () => performance.now(), wallNow: () => Date.now(), setTimeout, clearTimeout, setInterval, clearInterval};

export class RuntimeError extends Error {
    constructor(code) { super(code); this.code = code; }
}

function compare(actual, clause) {
    if (!scalar(actual)) return false;
    if (clause.operator === 'eq') return actual === clause.value;
    if (clause.operator === 'ne') return actual !== clause.value;
    if (typeof actual !== 'number' || typeof clause.value !== 'number') return false;
    return {gt: () => actual > clause.value, gte: () => actual >= clause.value,
        lt: () => actual < clause.value, lte: () => actual <= clause.value}[clause.operator]();
}

export class AutomationRuntime {
    constructor({zigbee, dispatch, logger, clock = nativeClock, available = true}) {
        this.zigbee = zigbee;
        this.dispatch = dispatch;
        this.logger = logger;
        this.clock = clock;
        this.available = available;
        this.generation = 0;
        this.active = null;
        this.rules = [];
        this.values = new Map();
        this.references = new Map();
        this.holds = new Map();
        this.waits = new Map();
        this.running = new Map();
        this.tasks = new Set();
        this.cooldowns = new Map();
        this.formats = new Map();
        this.days = new Map();
        this.sentAt = [];
        this.timer = null;
        this.lastError = '';
    }

    metadata() {
        return {runtime_version: 1, executing: this.active !== null, active_revision: this.active?.document.revision ?? null,
            active_checksum: this.active?.checksum ?? null, enabled_count: this.rules.length, runtime_error: this.lastError};
    }

    log(event, message, details = {}) {
        try {
            this.logger.debug?.('Z2MAutomations | ' + event + ' | ' + JSON.stringify({Meldung: message, Bezug: details}));
        } catch { /* A broken diagnostic sink cannot alter execution. */ }
    }

    device(id) {
        const entity = this.zigbee?.resolveEntity?.(id);
        return entity?.isDevice?.() && entity.ieeeAddr === id && entity.zh?.type !== 'Coordinator' ? entity : null;
    }

    target(target) {
        const entity = target.type === 'device' ? this.device(target.id)
            : [...(this.zigbee?.groupsIterator?.() ?? [])].find(group => group.ID === target.id);
        if (!entity || typeof entity.name !== 'string' || entity.name === '' || entity.name.length > 1024
            || /[\u0000-\u001f\u007f+#]/.test(entity.name) || /^bridge(?:\/|$)/.test(entity.name)) throw new RuntimeError('unknown_entity');
        return entity;
    }

    activate(candidate) {
        if (!this.available || !this.zigbee?.resolveEntity || typeof this.dispatch !== 'function') throw new RuntimeError('runtime_unavailable');
        const rules = candidate.document.automations.filter(rule => rule.enabled);
        const references = new Map();
        for (const rule of rules) {
            for (const clause of [...rule.triggers, ...rule.conditions]) {
                if (!clause.device) continue;
                if (!this.device(clause.device)) throw new RuntimeError('unknown_entity');
                if (!references.has(clause.device)) references.set(clause.device, new Set());
                references.get(clause.device).add(clause.property);
            }
            for (const action of rule.actions) this.target(action.target);
        }
        if (this.active?.checksum === candidate.checksum) return;
        this.deactivate();
        this.active = candidate;
        this.rules = rules;
        this.references = references;
        this.lastError = '';
        if (rules.some(rule => rule.triggers.some(trigger => trigger.type === 'time'))) {
            this.timer = this.clock.setInterval(() => this.tick(), 1000);
            this.timer?.unref?.();
        }
        this.log('AKTION', 'Gespeicherte Revision für neue Auslöser aktiviert.', {Revision: candidate.document.revision, Regeln: rules.length});
    }

    deactivate() {
        this.generation++;
        this.active = null;
        this.rules = [];
        if (this.timer !== null) this.clock.clearInterval(this.timer);
        this.timer = null;
        for (const [timer, resolve] of this.waits) { this.clock.clearTimeout(timer); resolve(false); }
        this.waits.clear();
        for (const hold of this.holds.values()) if (hold.timer !== null) this.clock.clearTimeout(hold.timer);
        this.holds.clear();
        this.values.clear(); this.references.clear(); this.cooldowns.clear(); this.days.clear(); this.formats.clear();
        this.running.clear();
        this.log('AKTION', 'Ausführung gestoppt; ausstehende Verzögerungen verworfen.');
    }

    async stop() { this.deactivate(); await Promise.allSettled([...this.tasks]); }

    local(timezone) {
        if (!this.formats.has(timezone)) this.formats.set(timezone, new Intl.DateTimeFormat('en-GB', {
            timeZone: timezone, year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23'
        }));
        const p = Object.fromEntries(this.formats.get(timezone).formatToParts(this.clock.wallNow()).map(part => [part.type, part.value]));
        return {day: `${p.year}-${p.month}-${p.day}`, time: `${p.hour}:${p.minute}:${p.second}`};
    }

    conditions(rule) {
        return rule.conditions.every(clause => {
            if (clause.type === 'device_value') return this.device(clause.device) && compare(this.values.get(clause.device)?.[clause.property], clause);
            const local = this.local(clause.timezone);
            const overnight = clause.after > clause.before;
            if (!(overnight ? local.time >= clause.after || local.time < clause.before : local.time >= clause.after && local.time < clause.before)) return false;
            const anchor = new Date(local.day + 'T00:00:00Z');
            if (overnight && local.time < clause.before) anchor.setUTCDate(anchor.getUTCDate() - 1);
            return !clause.weekdays || clause.weekdays.includes(anchor.getUTCDay() || 7);
        });
    }

    receive(event) {
        if (!this.active || !event?.entity?.isDevice?.() || !object(event.payload)
            || ['publishCached', 'lastSeenChanged'].includes(event.stateChangeReason)) return;
        const id = event.entity.ieeeAddr;
        const properties = this.references.get(id);
        if (!properties || !this.device(id)) return;
        const values = this.values.get(id) ?? Object.create(null);
        for (const property of properties) {
            if (Object.hasOwn(event.payload, property)) values[property] = scalar(event.payload[property]) ? event.payload[property] : undefined;
        }
        this.values.set(id, values);
        this.log('EMPFANG', 'Neue Eigenschaften einer konfigurierten Quelle ausgewertet.', {Eigenschaften: [...properties].filter(property => Object.hasOwn(event.payload, property)).length});
        for (const rule of this.rules) {
            rule.triggers.forEach((trigger, index) => {
                if (trigger.type !== 'device' || trigger.device !== id || !Object.hasOwn(event.payload, trigger.property)) return;
                const key = rule.id + ':' + index;
                const matches = compare(values[trigger.property], trigger);
                if (!trigger.for_seconds) { if (matches) this.launch(rule); return; }
                const prior = this.holds.get(key);
                if (!matches) {
                    if (prior?.timer !== null && prior?.timer !== undefined) this.clock.clearTimeout(prior.timer);
                    this.holds.delete(key);
                } else if (!prior) {
                    const generation = this.generation;
                    const hold = {timer: this.clock.setTimeout(() => {
                        hold.timer = null;
                        if (generation === this.generation && this.active && compare(this.values.get(id)?.[trigger.property], trigger)
                            && this.device(id)) this.launch(rule);
                    }, trigger.for_seconds * 1000)};
                    hold.timer?.unref?.();
                    this.holds.set(key, hold);
                }
            });
        }
    }

    tick() {
        if (!this.active) return;
        for (const rule of this.rules) rule.triggers.forEach((trigger, index) => {
            if (trigger.type !== 'time') return;
            const local = this.local(trigger.timezone);
            const key = rule.id + ':' + index;
            if (local.time === trigger.at && (!this.days.has(key) || local.day > this.days.get(key))) {
                this.days.set(key, local.day);
                this.launch(rule);
            }
        });
    }

    wait(milliseconds, generation) {
        if (!this.active || generation !== this.generation) return Promise.resolve(false);
        if (!milliseconds) return Promise.resolve(true);
        return new Promise(resolve => {
            const timer = this.clock.setTimeout(() => { this.waits.delete(timer); resolve(generation === this.generation && this.active !== null); }, milliseconds);
            timer?.unref?.();
            this.waits.set(timer, resolve);
        });
    }

    launch(rule) {
        const now = this.clock.now();
        const reason = !this.active ? 'inactive' : this.running.has(rule.id) ? 'already_running'
            : this.tasks.size >= RUNTIME_LIMITS.concurrentRules ? 'concurrency_limit'
                : now - (this.cooldowns.get(rule.id) ?? -Infinity) < RUNTIME_LIMITS.ruleCooldownMs ? 'cooldown'
                    : !this.conditions(rule) ? 'conditions_not_met' : '';
        if (reason) { this.log('AKTION', 'Regellauf nicht gestartet.', {Regel: rule.id, Grund: reason}); return; }
        const generation = this.generation;
        this.cooldowns.set(rule.id, now);
        this.log('START', 'Auslöser und Bedingungen erfüllt; Aktionsfolge begonnen.', {Regel: rule.id});
        // Schedule the task after registering it so synchronous feedback cannot re-enter this rule.
        const task = Promise.resolve().then(() => this.run(rule, generation)).catch(error => {
            this.lastError = error instanceof RuntimeError ? error.code : 'action_failed';
            this.log('FEHLER', 'Aktionsfolge abgebrochen; keine automatische Wiederholung.', {Regel: rule.id, Grund: this.lastError});
        }).finally(() => {
            if (this.running.get(rule.id) === task) this.running.delete(rule.id);
            this.tasks.delete(task);
            this.log('ENDE', 'Aktionsfolge beendet; Übergaben sind keine Gerätebestätigung.', {Regel: rule.id});
        });
        this.running.set(rule.id, task);
        this.tasks.add(task);
    }

    async run(rule, generation) {
        for (const action of rule.actions) {
            if (!await this.wait((action.delay_seconds ?? 0) * 1000, generation)) return;
            if (!this.active || this.generation !== generation) return;
            if (!this.conditions(rule)) { this.log('AKTION', 'Bedingung vor Aktion nicht mehr erfüllt.', {Regel: rule.id}); return; }
            const entity = this.target(action.target);
            const now = this.clock.now();
            this.sentAt = this.sentAt.filter(time => now - time < 1000);
            if (this.sentAt.length >= RUNTIME_LIMITS.commandsPerSecond) throw new RuntimeError('rate_limited');
            this.sentAt.push(now);
            this.log('SENDEN', 'Set-Aktion an native Z2M-Befehlsverarbeitung übergeben; Gerätewirkung nicht bestätigt.', {Regel: rule.id, Zielart: action.target.type, Eigenschaften: Object.keys(action.values).length});
            let timeout;
            try {
                await Promise.race([
                    Promise.resolve().then(() => {
                        if (this.active && this.generation === generation) return this.dispatch(entity.name + '/set', JSON.stringify(action.values));
                    }),
                    new Promise((_, reject) => { timeout = this.clock.setTimeout(() => reject(new RuntimeError('action_timeout')), RUNTIME_LIMITS.publishTimeoutMs); })
                ]);
            } finally { this.clock.clearTimeout(timeout); }
        }
    }
}


export const LIMITS = Object.freeze({yamlBytes: 131072, requestBytes: 1048576, queuedRequests: 16});
const PREFIX = 'symcon/automations';
const VALID_TRANSACTION = /^[A-Za-z0-9_-]{1,64}$/;
const ERRORS = Object.freeze({
    invalid_request: 'Invalid request envelope.',
    invalid_yaml: 'YAML must contain one bounded JSON-compatible document without anchors, aliases or tags.',
    invalid_schema: 'Automation configuration does not conform to schema version 1.',
    invalid_semantics: 'Duplicate automation ID, invalid timezone or empty time window.',
    session_mismatch: 'Request does not belong to the current extension session.',
    revision_conflict: 'Expected revision does not match the stored configuration.',
    revision_reused: 'New revision must be exactly the stored revision plus one.',
    storage_unavailable: 'Storage requires attention before configurations can be saved.',
    storage_error: 'Configuration could not be persisted.',
    busy: 'Too many requests are pending.',
    not_ready: 'Extension is not accepting requests.',
    runtime_unavailable: 'Required Z2M runtime interfaces are unavailable.',
    unknown_entity: 'A configured device or group is unavailable.',
    checksum_mismatch: 'Activation checksum does not match the stored configuration.',
    activation_expired: 'Request a fresh activation token before activating the stored revision.'
});

class ConfigurationError extends Error {
    constructor(code) {
        super(ERRORS[code]);
        this.code = code;
    }
}

const fail = code => { throw new ConfigurationError(code); };
const isObject = value => value !== null && typeof value === 'object' && !Array.isArray(value);
const isRevision = value => Number.isSafeInteger(value) && value >= 0;
const hash = text => createHash('sha256').update(text, 'utf8').digest('hex');
const validateSchema = new Ajv({strict: true, allowUnionTypes: true, allErrors: false}).compile(AUTOMATIONS_SCHEMA);
const yamlSchema = JSON_SCHEMA.withTags({
    ...mapTag,
    create: () => Object.create(null),
    addPair: (container, key, value) => {
        if (typeof key !== 'string' || ['__proto__', 'constructor', 'prototype', '<<'].includes(key)) return 'Unsupported mapping key';
        container[key] = value;
        return '';
    },
    has: (container, key) => Object.hasOwn(container, key),
    get: (container, key) => container[key]
});

export function validateYaml(yaml) {
    if (typeof yaml !== 'string' || yaml.length === 0 || Buffer.byteLength(yaml, 'utf8') > LIMITS.yamlBytes
        || Buffer.from(yaml, 'utf8').toString('utf8') !== yaml) fail('invalid_yaml');
    let document;
    try {
        const events = parseEvents(yaml, {maxDepth: 16});
        if (events.length > 20000 || events.filter(event => event.type === EVENT_ID.DOCUMENT).length !== 1) fail('invalid_yaml');
        for (const event of events) {
            if (event.type === EVENT_ID.ALIAS || event.anchorStart >= 0 || event.tagStart >= 0
                || (event.directives && event.directives.length !== 0)) fail('invalid_yaml');
        }
        [document] = constructFromEvents(events, {source: yaml, schema: yamlSchema, json: false, maxAliases: 0, maxTotalMergeKeys: 0});
    } catch {
        fail('invalid_yaml');
    }
    if (!validateSchema(document)) fail('invalid_schema');
    const ids = new Set();
    for (const automation of document.automations) {
        if (ids.has(automation.id)) fail('invalid_semantics');
        ids.add(automation.id);
        for (const clause of [...automation.triggers, ...automation.conditions]) {
            if (clause.timezone !== undefined) {
                try {
                    new Intl.DateTimeFormat('en', {timeZone: clause.timezone}).format(0);
                } catch {
                    fail('invalid_semantics');
                }
            }
            if (clause.type === 'time_window' && clause.after === clause.before) fail('invalid_semantics');
        }
    }
    return {yaml, document, checksum: hash(yaml)};
}

// Only discrete exposed values enter the click editor; raw definitions stay in Z2M.
export function buildEditorCatalog(zigbee) {
    const candidates = [];
    let optionCount = 0, devicesSeen = 0;
    const scalar = value => value === null || typeof value === 'boolean' || (typeof value === 'number' && Number.isFinite(value))
        || (typeof value === 'string' && [...value].length <= 256);
    for (const device of zigbee?.devicesIterator?.() ?? []) {
        if (++devicesSeen > 256) break;
        if (!device?.isDevice?.() || device.zh?.type === 'Coordinator' || !/^0x[0-9a-f]{16}$/.test(device.ieeeAddr)) continue;
        const triggers = [], actions = [], numeric = [];
        let visited = 0;
        const walk = (features, depth = 0, primary = false) => {
            if (!Array.isArray(features) || depth > 6) return;
            for (const feature of features) {
                if (++visited > 128 || optionCount >= 1024) break;
                if (!isObject(feature)) continue;
                walk(feature.features, depth + 1, primary);
                if (typeof feature.property !== 'string' || !/^[A-Za-z][A-Za-z0-9_]{0,63}$/.test(feature.property)
                    || ['constructor', 'prototype', '__proto__'].includes(feature.property)) continue;
                const state = feature.type === 'binary' && (feature.property === 'state' || feature.name === 'state'
                    || (typeof feature.endpoint === 'string' && feature.property === 'state_' + feature.endpoint));
                if (state !== primary) continue;
                if (feature.type === 'numeric' && (feature.access & 1) !== 0 && numeric.length < 64 && optionCount < 1024) {
                    numeric.push({property: feature.property, unit: typeof feature.unit === 'string' ? [...feature.unit].slice(0, 24).join('') : ''});
                    optionCount++;
                }
                const values = feature.type === 'binary' ? [feature.value_on, feature.value_off]
                    : feature.type === 'enum' && Array.isArray(feature.values) ? feature.values.slice(0, 32) : [];
                for (const value of values) {
                    if (!scalar(value) || optionCount >= 1024) continue;
                    const entry = {property: feature.property, value};
                    if ((feature.access & 1) !== 0 && triggers.length < 64 && optionCount < 1024) { triggers.push(entry); optionCount++; }
                    if ((feature.access & 2) !== 0 && actions.length < 64 && optionCount < 1024) { actions.push(entry); optionCount++; }
                }
            }
        };
        let features;
        try { features = device.exposes(); } catch { continue; }
        candidates.push({entry: {id: device.ieeeAddr,
            name: typeof device.name === 'string' ? [...device.name].slice(0, 120).join('') : device.ieeeAddr, triggers, actions, numeric},
        scan: primary => { visited = 0; walk(features, 0, primary); }, failed: false});
    }
    // Reserve ordinary on/off choices across the bounded device set before special options.
    for (const primary of [true, false]) {
        for (const candidate of candidates) {
            if (candidate.failed) continue;
            try { candidate.scan(primary); } catch { candidate.failed = true; }
        }
    }
    return candidates.filter(({entry, failed}) => !failed && (entry.triggers.length || entry.actions.length || entry.numeric.length)).map(({entry}) => entry);
}

class ConfigurationStore {
    constructor(directory, io = fs) {
        this.directory = directory;
        this.io = io;
        this.current = null;
        this.state = 'empty';
    }

    metadata() {
        return {stored_revision: this.current?.document.revision ?? 0, checksum: this.current?.checksum ?? null,
            automation_count: this.current?.document.automations.length ?? 0, storage: this.state, executing: false};
    }

    async read(name) {
        const path = join(this.directory, name);
        let handle;
        try {
            const stat = await this.io.lstat(path);
            if (!stat.isFile() || stat.isSymbolicLink() || stat.size > LIMITS.yamlBytes) fail('storage_unavailable');
            handle = await this.io.open(path, 'r');
            const bytes = await handle.readFile();
            if (bytes.length > LIMITS.yamlBytes) fail('storage_unavailable');
            return validateYaml(new TextDecoder('utf-8', {fatal: true}).decode(bytes));
        } finally {
            await handle?.close();
        }
    }

    async load() {
        this.current = null;
        this.state = 'empty';
        try {
            await this.io.mkdir(this.directory, {recursive: true, mode: 0o700});
            const directory = await this.io.lstat(this.directory);
            if (!directory.isDirectory() || directory.isSymbolicLink()) fail('storage_unavailable');
        } catch {
            this.state = 'error';
            return;
        }
        try {
            this.current = await this.read('automations.yaml');
            this.state = 'ok';
        } catch (error) {
            const missing = error.code === 'ENOENT';
            try {
                this.current = await this.read('automations.previous.yaml');
                // Never silently roll revision history back after a damaged/missing primary file.
                this.state = 'recovered_read_only';
            } catch (backupError) {
                this.state = missing && backupError.code === 'ENOENT' ? 'empty' : 'error';
            }
        }
    }

    async atomicWrite(name, yaml) {
        const temporary = join(this.directory, '.write-' + randomUUID() + '.tmp');
        let handle;
        let committed = false;
        try {
            handle = await this.io.open(temporary, 'wx', 0o600);
            await handle.writeFile(yaml, 'utf8');
            await handle.sync();
            await handle.close();
            handle = null;
            await this.io.rename(temporary, join(this.directory, name));
            committed = true;
        } finally {
            await handle?.close().catch(() => {});
            if (!committed) await this.io.unlink(temporary).catch(() => {});
        }
    }

    async verifyDisk() {
        try {
            // Verify that the primary has not been edited outside this extension since loading.
            if (this.current) {
                const disk = await this.read('automations.yaml');
                if (disk.checksum !== this.current.checksum) fail('storage_unavailable');
            } else {
                try {
                    await this.io.lstat(join(this.directory, 'automations.yaml'));
                    fail('storage_unavailable');
                } catch (error) {
                    if (error.code !== 'ENOENT') throw error;
                }
            }
        } catch {
            this.state = 'error';
            fail('storage_unavailable');
        }
    }

    async save(candidate, expectedRevision) {
        if (!['ok', 'empty'].includes(this.state)) fail('storage_unavailable');
        await this.verifyDisk();
        const revision = this.current?.document.revision ?? 0;
        if (candidate.document.revision === revision && candidate.checksum === this.current?.checksum
            && [revision, revision - 1].includes(expectedRevision)) {
            return {...this.metadata(), unchanged: true};
        }
        if (expectedRevision !== revision) fail('revision_conflict');
        if (candidate.document.revision !== revision + 1) fail('revision_reused');
        try {
            if (this.current) await this.atomicWrite('automations.previous.yaml', this.current.yaml);
            await this.atomicWrite('automations.yaml', candidate.yaml);
        } catch {
            fail('storage_error');
        }
        this.current = candidate;
        this.state = 'ok';
        return {...this.metadata(), unchanged: false};
    }
}

export default class Z2MAutomationsExtension {
    constructor(_zigbee, mqtt, _state, _publishEntityState, eventBus, _enableDisableExtension, _restartCallback, _addExtension, settings, logger, options = {}) {
        this.mqtt = mqtt;
        this.eventBus = eventBus;
        this.settings = settings;
        this.logger = logger;
        this.zigbee = _zigbee;
        this.runtime = new AutomationRuntime({zigbee: _zigbee, logger, clock: options.clock,
            dispatch: (topic, message) => this.eventBus.emitMQTTMessage({topic: this.baseTopic + '/' + topic, message}),
            available: typeof eventBus.onPublishEntityState === 'function' && typeof eventBus.emitMQTTMessage === 'function'});
        // A real deployed file is in data/external_extensions, including Z2M temporary import copies.
        const directory = options.directory ?? fileURLToPath(new URL('../z2m-automations/', import.meta.url));
        this.store = new ConfigurationStore(directory, options.io ?? fs);
        this.queue = Promise.resolve();
        this.pending = 0;
        this.accepting = false;
        this.session = '';
        this.activationToken = null;
        this.monotonicNow = options.clock?.now ?? (() => performance.now());
    }

    status() {
        return {protocol_version: 1, schema_version: 1, extension_version: '0.1.0',
            session: this.session, state: this.accepting ? 'ready' : 'stopped', ...this.store.metadata(), ...this.runtime.metadata()};
    }

    async publish(topic, data, retain = false) {
        try {
            await this.mqtt.publish(PREFIX + '/' + topic, JSON.stringify(data), {clientOptions: {retain, qos: 0}, skipLog: true});
        } catch {
            this.runtime.log('FEHLER', 'MQTT-Antwort konnte nicht veröffentlicht werden.');
        }
    }

    async start() {
        this.runtime.log('START', 'Erweiterung starten.');
        try {
            if (this.accepting) return;
            this.baseTopic = this.settings.get().mqtt.base_topic;
            this.session = randomUUID();
            this.activationToken = null;
            await this.store.load();
            this.accepting = true;
            this.eventBus.onMQTTMessage(this, message => this.receive(message));
            this.eventBus.onPublishEntityState?.(this, event => {
                try { this.runtime.receive(event); }
                catch { this.runtime.log('FEHLER', 'Geräteereignis verworfen; interne Verarbeitung fehlgeschlagen.'); }
            });
            await this.publish('status', this.status(), true);
        } finally { this.runtime.log('ENDE', 'Startaufruf beendet.', {Bereit: this.accepting}); }
    }

    async stop() {
        this.runtime.log('START', 'Erweiterung stoppen.');
        try {
            this.accepting = false;
            this.activationToken = null;
            this.eventBus.removeListeners(this);
            this.runtime.deactivate();
            await this.queue;
            await this.runtime.stop();
            await this.publish('status', this.status(), true);
        } finally { this.runtime.log('ENDE', 'Stoppaufruf beendet.', {Bereit: this.accepting}); }
    }

    async receive(event) {
        const prefix = this.baseTopic + '/' + PREFIX + '/request/';
        if (typeof event?.topic !== 'string' || !event.topic.startsWith(prefix)) return;
        const operation = event.topic.slice(prefix.length);
        if (!['validate', 'store', 'get', 'status', 'activate', 'deactivate', 'editor'].includes(operation)) return;
        this.runtime.log('START', 'Erweiterungsauftrag empfangen.', {Auftrag: operation});
        try { await this.handleRequest(operation, event); }
        finally { this.runtime.log('ENDE', 'Erweiterungsauftrag beendet; Ergebnis siehe Antwort.', {Auftrag: operation}); }
    }

    async handleRequest(operation, event) {
        let request;
        try {
            if (typeof event.message !== 'string' || Buffer.byteLength(event.message, 'utf8') > LIMITS.requestBytes) fail('invalid_request');
            request = JSON.parse(event.message);
            if (!isObject(request) || request.protocol_version !== 1 || typeof request.transaction !== 'string'
                || !VALID_TRANSACTION.test(request.transaction)) fail('invalid_request');
            const keys = ['protocol_version', 'transaction', ...(['validate', 'store'].includes(operation) ? ['yaml'] : []),
                ...(operation === 'store' ? ['session', 'expected_revision'] : []),
                ...(operation === 'activate' ? ['session', 'expected_revision', 'checksum', 'activation_token'] : []),
                ...(operation === 'deactivate' ? ['session'] : [])];
            if (Object.keys(request).length !== keys.length || keys.some(key => !Object.hasOwn(request, key))) fail('invalid_request');
        } catch {
            this.runtime.log('FEHLER', 'Ungültigen Erweiterungsauftrag abgewiesen.', {Auftrag: operation, Grund: 'invalid_request'});
            const transaction = typeof request?.transaction === 'string' && VALID_TRANSACTION.test(request.transaction) ? request.transaction : undefined;
            await this.publish('response/' + operation, {protocol_version: 1, transaction, status: 'error', error: {code: 'invalid_request', message: ERRORS.invalid_request}});
            return;
        }
        if (!this.accepting || this.pending >= LIMITS.queuedRequests) {
            const code = this.accepting ? 'busy' : 'not_ready';
            await this.respond(operation, request.transaction, null, code);
            return;
        }
        this.pending++;
        const task = this.queue.then(async () => {
            try {
                if (!this.accepting) fail('not_ready');
                let data;
                if (operation === 'status') {
                    this.activationToken = {value: randomUUID(), expires: this.monotonicNow() + 30000};
                    // Only a correlated status response carries this one-use challenge, never retained status.
                    data = {...this.status(), activation_token: this.activationToken.value};
                }
                if (operation === 'get') data = {...this.store.metadata(), executing: this.runtime.metadata().executing, yaml: this.store.current?.yaml ?? null};
                if (operation === 'editor') data = {...this.status(), editor_version: 1, devices: buildEditorCatalog(this.zigbee), yaml: this.store.current?.yaml ?? null,
                    configuration: this.store.current?.document ?? {schema_version: 1, revision: 0, automations: []}};
                if (operation === 'validate') {
                    const candidate = validateYaml(request.yaml);
                    data = {valid: true, revision: candidate.document.revision, checksum: candidate.checksum,
                        automation_count: candidate.document.automations.length, executing: false};
                }
                if (operation === 'store') {
                    if (request.session !== this.session || event.retain === true) fail('session_mismatch');
                    if (!isRevision(request.expected_revision)) fail('invalid_request');
                    data = await this.store.save(validateYaml(request.yaml), request.expected_revision);
                    this.activationToken = null;
                    if (!data.unchanged) this.runtime.deactivate();
                    data.executing = this.runtime.metadata().executing;
                }
                if (['activate', 'deactivate'].includes(operation)) {
                    if (request.session !== this.session || event.retain === true) fail('session_mismatch');
                    if (operation === 'activate') {
                        if (!isRevision(request.expected_revision) || typeof request.checksum !== 'string' || !/^[a-f0-9]{64}$/.test(request.checksum)) fail('invalid_request');
                        const token = this.activationToken;
                        this.activationToken = null;
                        if (!token || request.activation_token !== token.value || token.expires <= this.monotonicNow()) fail('activation_expired');
                        if (this.store.state !== 'ok' || !this.store.current) fail('storage_unavailable');
                        await this.store.verifyDisk();
                        if (!this.accepting) fail('not_ready');
                        if (request.expected_revision !== this.store.current.document.revision) fail('revision_conflict');
                        if (request.checksum !== this.store.current.checksum) fail('checksum_mismatch');
                        this.runtime.activate(this.store.current);
                    } else {
                        this.activationToken = null;
                        this.runtime.deactivate();
                    }
                    data = this.status();
                }
                await this.respond(operation, request.transaction, data);
                if (['store', 'activate', 'deactivate'].includes(operation)) await this.publish('status', this.status(), true);
            } catch (error) {
                if (this.store.state === 'error') this.runtime.deactivate();
                const code = error instanceof ConfigurationError || error instanceof RuntimeError ? error.code : 'storage_error';
                this.runtime.log('FEHLER', 'Erweiterungsauftrag abgewiesen.', {Auftrag: operation, Grund: code});
                await this.respond(operation, request.transaction, null, code);
            } finally {
                this.pending--;
            }
        });
        // Keep the next request usable even when a transport or test boundary rejects.
        this.queue = task.catch(() => {});
        await task;
    }

    async respond(operation, transaction, data, code = null) {
        await this.publish('response/' + operation, code === null
            ? {protocol_version: 1, transaction, status: 'ok', data}
            : {protocol_version: 1, transaction, status: 'error', error: {code, message: ERRORS[code]}});
    }
}
