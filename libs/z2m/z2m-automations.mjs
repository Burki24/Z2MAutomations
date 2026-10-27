// Generated from src/extension.mjs and automations.schema.json. Do not edit.
import {createHash, randomUUID} from 'node:crypto';
import * as fs from 'node:fs/promises';
import {join} from 'node:path';
import {fileURLToPath} from 'node:url';
import {TextDecoder} from 'node:util';
import Ajv from 'ajv';
import {parseEvents, constructFromEvents, JSON_SCHEMA, mapTag, EVENT_ID} from 'js-yaml';
const AUTOMATIONS_SCHEMA = {"$schema":"http://json-schema.org/draft-07/schema#","$id":"urn:burki24:z2m-automations:schema:1","title":"Z2MAutomations configuration v1","type":"object","additionalProperties":false,"properties":{"schema_version":{"const":1},"revision":{"type":"integer","minimum":1,"maximum":9007199254740991},"automations":{"type":"array","maxItems":100,"items":{"$ref":"#/definitions/automation"}}},"required":["schema_version","revision","automations"],"definitions":{"device":{"type":"string","pattern":"^0x[0-9a-f]{16}$"},"jsonValue":{"anyOf":[{"type":["string","number","boolean","null"],"maxLength":256},{"type":"array","maxItems":32,"items":{"$ref":"#/definitions/jsonValue"}},{"type":"object","maxProperties":32,"propertyNames":{"type":"string","pattern":"^[A-Za-z][A-Za-z0-9_]{0,63}$","not":{"enum":["__proto__","constructor","prototype"]}},"additionalProperties":{"$ref":"#/definitions/jsonValue"}}]},"deviceTrigger":{"type":"object","additionalProperties":false,"properties":{"type":{"const":"device"},"device":{"$ref":"#/definitions/device"},"property":{"type":"string","pattern":"^[A-Za-z][A-Za-z0-9_]{0,63}$","not":{"enum":["__proto__","constructor","prototype"]}},"operator":{"enum":["eq","ne","gt","gte","lt","lte"]},"value":{"type":["string","number","boolean","null"],"maxLength":256},"for_seconds":{"type":"integer","minimum":0,"maximum":86400}},"required":["type","device","property","operator","value"],"allOf":[{"if":{"properties":{"operator":{"enum":["gt","gte","lt","lte"]}}},"then":{"properties":{"value":{"type":"number"}}}}]},"timeTrigger":{"type":"object","additionalProperties":false,"properties":{"type":{"const":"time"},"at":{"type":"string","pattern":"^([01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$"},"timezone":{"type":"string","minLength":1,"maxLength":64}},"required":["type","at","timezone"]},"deviceCondition":{"type":"object","additionalProperties":false,"properties":{"type":{"const":"device_value"},"device":{"$ref":"#/definitions/device"},"property":{"type":"string","pattern":"^[A-Za-z][A-Za-z0-9_]{0,63}$","not":{"enum":["__proto__","constructor","prototype"]}},"operator":{"enum":["eq","ne","gt","gte","lt","lte"]},"value":{"type":["string","number","boolean","null"],"maxLength":256}},"required":["type","device","property","operator","value"],"allOf":[{"if":{"properties":{"operator":{"enum":["gt","gte","lt","lte"]}}},"then":{"properties":{"value":{"type":"number"}}}}]},"timeCondition":{"type":"object","additionalProperties":false,"properties":{"type":{"const":"time_window"},"after":{"type":"string","pattern":"^([01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$"},"before":{"type":"string","pattern":"^([01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$"},"timezone":{"type":"string","minLength":1,"maxLength":64},"weekdays":{"type":"array","minItems":1,"maxItems":7,"uniqueItems":true,"items":{"type":"integer","minimum":1,"maximum":7}}},"required":["type","after","before","timezone"]},"target":{"oneOf":[{"type":"object","additionalProperties":false,"properties":{"type":{"const":"device"},"id":{"$ref":"#/definitions/device"}},"required":["type","id"]},{"type":"object","additionalProperties":false,"properties":{"type":{"const":"group"},"id":{"type":"integer","minimum":1,"maximum":65535}},"required":["type","id"]}]},"action":{"type":"object","additionalProperties":false,"properties":{"type":{"const":"set"},"target":{"$ref":"#/definitions/target"},"values":{"type":"object","minProperties":1,"maxProperties":32,"propertyNames":{"type":"string","pattern":"^[A-Za-z][A-Za-z0-9_]{0,63}$","not":{"enum":["__proto__","constructor","prototype"]}},"additionalProperties":{"$ref":"#/definitions/jsonValue"}},"delay_seconds":{"type":"integer","minimum":0,"maximum":86400}},"required":["type","target","values"]},"automation":{"type":"object","additionalProperties":false,"properties":{"id":{"type":"string","pattern":"^[a-z][a-z0-9_-]{0,63}$"},"name":{"type":"string","minLength":1,"maxLength":120},"enabled":{"type":"boolean"},"triggers":{"type":"array","minItems":1,"maxItems":8,"items":{"oneOf":[{"$ref":"#/definitions/deviceTrigger"},{"$ref":"#/definitions/timeTrigger"}]}},"conditions":{"type":"array","maxItems":16,"items":{"oneOf":[{"$ref":"#/definitions/deviceCondition"},{"$ref":"#/definitions/timeCondition"}]}},"actions":{"type":"array","minItems":1,"maxItems":16,"items":{"$ref":"#/definitions/action"}}},"required":["id","name","enabled","triggers","conditions","actions"]}}};

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
    not_ready: 'Extension is not accepting requests.'
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
        // A real deployed file is in data/external_extensions, including Z2M temporary import copies.
        const directory = options.directory ?? fileURLToPath(new URL('../z2m-automations/', import.meta.url));
        this.store = new ConfigurationStore(directory, options.io ?? fs);
        this.queue = Promise.resolve();
        this.pending = 0;
        this.accepting = false;
        this.session = '';
    }

    status() {
        return {protocol_version: 1, schema_version: 1, extension_version: '0.1.0',
            session: this.session, state: this.accepting ? 'ready' : 'stopped', ...this.store.metadata()};
    }

    async publish(topic, data, retain = false) {
        try {
            await this.mqtt.publish(PREFIX + '/' + topic, JSON.stringify(data), {clientOptions: {retain, qos: 0}, skipLog: true});
        } catch {
            this.logger.error('Z2MAutomations: MQTT response could not be published.');
        }
    }

    async start() {
        if (this.accepting) return;
        this.baseTopic = this.settings.get().mqtt.base_topic;
        this.session = randomUUID();
        await this.store.load();
        this.accepting = true;
        this.eventBus.onMQTTMessage(this, message => this.receive(message));
        await this.publish('status', this.status(), true);
    }

    async stop() {
        this.accepting = false;
        this.eventBus.removeListeners(this);
        await this.queue;
        await this.publish('status', this.status(), true);
    }

    async receive(event) {
        const prefix = this.baseTopic + '/' + PREFIX + '/request/';
        if (typeof event?.topic !== 'string' || !event.topic.startsWith(prefix)) return;
        const operation = event.topic.slice(prefix.length);
        if (!['validate', 'store', 'get', 'status'].includes(operation)) return;
        let request;
        try {
            if (typeof event.message !== 'string' || Buffer.byteLength(event.message, 'utf8') > LIMITS.requestBytes) fail('invalid_request');
            request = JSON.parse(event.message);
            if (!isObject(request) || request.protocol_version !== 1 || typeof request.transaction !== 'string'
                || !VALID_TRANSACTION.test(request.transaction)) fail('invalid_request');
            const keys = ['protocol_version', 'transaction', ...(['validate', 'store'].includes(operation) ? ['yaml'] : []),
                ...(operation === 'store' ? ['session', 'expected_revision'] : [])];
            if (Object.keys(request).length !== keys.length || keys.some(key => !Object.hasOwn(request, key))) fail('invalid_request');
        } catch {
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
                if (operation === 'status') data = this.status();
                if (operation === 'get') data = {...this.store.metadata(), yaml: this.store.current?.yaml ?? null};
                if (operation === 'validate') {
                    const candidate = validateYaml(request.yaml);
                    data = {valid: true, revision: candidate.document.revision, checksum: candidate.checksum,
                        automation_count: candidate.document.automations.length, executing: false};
                }
                if (operation === 'store') {
                    if (request.session !== this.session || event.retain === true) fail('session_mismatch');
                    if (!isRevision(request.expected_revision)) fail('invalid_request');
                    data = await this.store.save(validateYaml(request.yaml), request.expected_revision);
                }
                await this.respond(operation, request.transaction, data);
                if (operation === 'store') await this.publish('status', this.status(), true);
            } catch (error) {
                const code = error instanceof ConfigurationError ? error.code : 'storage_error';
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
