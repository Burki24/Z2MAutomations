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
    constructor({zigbee, dispatch, logger, clock = nativeClock, available = true, readState}) {
        this.zigbee = zigbee;
        this.dispatch = dispatch;
        this.readState = readState;
        this.logger = logger;
        this.clock = clock;
        this.available = available;
        this.generation = 0;
        this.active = null;
        this.rules = [];
        this.values = new Map();
        this.changes = new Map();
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
        const changes = new Map();
        for (const rule of rules) {
            for (const clause of [...rule.triggers, ...rule.conditions]) {
                if (!clause.device) continue;
                if (!this.device(clause.device)) throw new RuntimeError('unknown_entity');
                if (!references.has(clause.device)) references.set(clause.device, new Set());
                references.get(clause.device).add(clause.property);
                if (clause.on_change) {
                    if (typeof this.readState !== 'function') throw new RuntimeError('runtime_unavailable');
                    const cached = this.readState(this.device(clause.device));
                    if (!changes.has(clause.device)) changes.set(clause.device, Object.create(null));
                    if (object(cached) && Object.hasOwn(cached, clause.property) && scalar(cached[clause.property])) {
                        changes.get(clause.device)[clause.property] = cached[clause.property];
                    }
                }
            }
            for (const action of rule.actions) {
                this.target(action.target);
                if (action.type === 'toggle' && typeof this.readState !== 'function') throw new RuntimeError('runtime_unavailable');
            }
        }
        if (this.active?.checksum === candidate.checksum) return;
        this.deactivate();
        this.active = candidate;
        this.rules = rules;
        this.references = references;
        this.changes = changes;
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
        this.values.clear();
        this.changes.clear();
        this.references.clear(); this.cooldowns.clear(); this.days.clear(); this.formats.clear();
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
        const previous = {...(this.changes.get(id) ?? {})};
        for (const property of properties) {
            if (Object.hasOwn(event.payload, property)) values[property] = scalar(event.payload[property]) ? event.payload[property] : undefined;
        }
        this.values.set(id, values);
        this.changes.set(id, {...previous, ...values});
        this.log('EMPFANG', 'Neue Eigenschaften einer konfigurierten Quelle ausgewertet.', {Eigenschaften: [...properties].filter(property => Object.hasOwn(event.payload, property)).length});
        for (const rule of this.rules) {
            rule.triggers.forEach((trigger, index) => {
                if (trigger.type !== 'device' || trigger.device !== id || !Object.hasOwn(event.payload, trigger.property)) return;
                const key = rule.id + ':' + index;
                const matches = compare(values[trigger.property], trigger);
                const changed = !Object.hasOwn(previous, trigger.property) || previous[trigger.property] !== values[trigger.property];
                if (matches && trigger.on_change && !changed) this.log('AKTION', 'Wert unverändert; Auslöser verlangt eine Wertänderung.', {Regel: rule.id, Auslöser: index + 1});
                if (!trigger.for_seconds) { if (matches && (!trigger.on_change || changed)) this.launch(rule); return; }
                const prior = this.holds.get(key);
                if (!matches) {
                    if (prior?.timer !== null && prior?.timer !== undefined) this.clock.clearTimeout(prior.timer);
                    this.holds.delete(key);
                } else if (!prior && (!trigger.on_change || changed)) {
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
            let values = action.values;
            if (action.type === 'toggle') {
                const current = this.readState(entity)?.[action.property];
                if (current !== 'ON' && current !== 'OFF') throw new RuntimeError('state_unavailable');
                values = {[action.property]: current === 'ON' ? 'OFF' : 'ON'};
                this.log('AKTION', 'Umschaltziel aus dem aktuellen Z2M-Zustand ermittelt.', {Regel: rule.id, Zielart: action.target.type});
            }
            const now = this.clock.now();
            this.sentAt = this.sentAt.filter(time => now - time < 1000);
            if (this.sentAt.length >= RUNTIME_LIMITS.commandsPerSecond) throw new RuntimeError('rate_limited');
            this.sentAt.push(now);
            this.log('SENDEN', 'Set-Aktion an native Z2M-Befehlsverarbeitung übergeben; Gerätewirkung nicht bestätigt.', {Regel: rule.id, Zielart: action.target.type, Eigenschaften: Object.keys(values).length});
            let timeout;
            try {
                await Promise.race([
                    Promise.resolve().then(() => {
                        if (this.active && this.generation === generation) return this.dispatch(entity.name + '/set', JSON.stringify(values));
                    }),
                    new Promise((_, reject) => { timeout = this.clock.setTimeout(() => reject(new RuntimeError('action_timeout')), RUNTIME_LIMITS.publishTimeoutMs); })
                ]);
            } finally { this.clock.clearTimeout(timeout); }
        }
    }
}
