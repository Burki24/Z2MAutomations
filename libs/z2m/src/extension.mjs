import {createHash, randomUUID} from 'node:crypto';
import * as fs from 'node:fs/promises';
import {join} from 'node:path';
import {fileURLToPath} from 'node:url';
import {TextDecoder} from 'node:util';
import Ajv from 'ajv';
import {parseEvents, constructFromEvents, JSON_SCHEMA, mapTag, EVENT_ID} from 'js-yaml';
import AUTOMATIONS_SCHEMA from '../automations.schema.json' with {type: 'json'};
import {AutomationRuntime, RuntimeError} from './runtime.mjs';

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
        const triggers = [], actions = [], numeric = [], properties = [];
        let visited = 0;
        const walk = (features, depth = 0, phase = 0, endpoint = '') => {
            if (!Array.isArray(features) || depth > 6) return;
            for (const feature of features) {
                if (++visited > 128 || optionCount >= 1024) break;
                if (!isObject(feature)) continue;
                const channel = typeof feature.endpoint === 'string' ? feature.endpoint : endpoint;
                walk(feature.features, depth + 1, phase, channel);
                if (typeof feature.property !== 'string' || !/^[A-Za-z][A-Za-z0-9_]{0,63}$/.test(feature.property)
                    || ['constructor', 'prototype', '__proto__'].includes(feature.property)) continue;
                const state = feature.type === 'binary' && (feature.property === 'state' || feature.name === 'state'
                    || (typeof feature.endpoint === 'string' && feature.property === 'state_' + feature.endpoint));
                const priority = state ? 0 : feature.type === 'enum' && /^(?:state|action)(?:_[A-Za-z0-9_]+)?$/.test(feature.property) ? 1 : 2;
                if (priority !== phase) continue;
                const admitted = [];
                const before = optionCount;
                if (feature.type === 'numeric' && (feature.access & 3) !== 0 && numeric.length < 64 && optionCount < 1024) {
                    if ((feature.access & 1) !== 0) {
                        numeric.push({property: feature.property, unit: typeof feature.unit === 'string' ? [...feature.unit].slice(0, 24).join('') : ''});
                    }
                    optionCount++;
                }
                const values = feature.type === 'binary' ? [feature.value_on, feature.value_off]
                    : feature.type === 'enum' && Array.isArray(feature.values) ? feature.values.slice(0, 32) : [];
                for (const value of values) {
                    if (!scalar(value) || optionCount >= 1024) continue;
                    const entry = {property: feature.property, value};
                    const prior = optionCount;
                    if ((feature.access & 1) !== 0 && triggers.length < 64 && optionCount < 1024) { triggers.push(entry); optionCount++; }
                    if ((feature.access & 2) !== 0 && actions.length < 64 && optionCount < 1024) { actions.push(entry); optionCount++; }
                    if (optionCount > prior && !admitted.includes(value)) admitted.push(value);
                }
                if (optionCount > before && properties.length < 64 && ['binary', 'enum', 'numeric'].includes(feature.type)
                    && !properties.some(item => item.property === feature.property)) {
                    const property = {property: feature.property, type: feature.type, access: feature.access & 3,
                        label: typeof feature.label === 'string' && !/[\x00-\x1f\x7f]/.test(feature.label) ? [...feature.label].slice(0, 80).join('') : feature.property,
                        endpoint: typeof channel === 'string' && /^[A-Za-z0-9_-]{1,32}$/.test(channel) ? channel : '', values: admitted};
                    if (feature.type === 'numeric') {
                        for (const [from, to] of [['value_min', 'minimum'], ['value_max', 'maximum'], ['value_step', 'step']]) {
                            if (typeof feature[from] === 'number' && Number.isFinite(feature[from]) && (to !== 'step' || feature[from] > 0)) property[to] = feature[from];
                        }
                    }
                    properties.push(property);
                }
            }
        };
        let features;
        try { features = device.exposes(); } catch { continue; }
        candidates.push({entry: {id: device.ieeeAddr,
            name: typeof device.name === 'string' ? [...device.name].slice(0, 120).join('') : device.ieeeAddr, triggers, actions, numeric, properties},
        scan: phase => { visited = 0; walk(features, 0, phase); }, failed: false});
    }
    // Reserve switch channels first, then button events and cover commands, before special options.
    for (const phase of [0, 1, 2]) {
        for (const candidate of candidates) {
            if (candidate.failed) continue;
            try { candidate.scan(phase); } catch { candidate.failed = true; }
        }
    }
    return candidates.filter(({entry, failed}) => !failed && (entry.triggers.length || entry.actions.length || entry.numeric.length || entry.properties.length)).map(({entry}) => entry);
}

// Only stable group identity and display name cross the editor boundary.
export function buildEditorGroups(zigbee) {
    const groups = [], seen = new Set();
    let visited = 0;
    for (const group of zigbee?.groupsIterator?.() ?? []) {
        if (++visited > 256) break;
        if (!Number.isInteger(group.ID) || group.ID < 1 || group.ID > 65535 || seen.has(group.ID)
            || typeof group.name !== 'string' || !group.name || /[\x00-\x1f\x7f+#]/.test(group.name)) continue;
        seen.add(group.ID);
        groups.push({id: group.ID, name: [...group.name].slice(0, 120).join('')});
    }
    return groups;
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
            readState: typeof _state?.get === 'function' ? entity => _state.get(entity) : undefined,
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
                if (operation === 'editor') data = {...this.status(), editor_version: 1, devices: buildEditorCatalog(this.zigbee), groups: buildEditorGroups(this.zigbee), yaml: this.store.current?.yaml ?? null,
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
