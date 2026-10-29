import {createHash, randomUUID} from 'node:crypto';
import {RuntimeError} from './runtime.mjs';

// Library-local bridge protocol. Credentials and broker configuration stay outside Z2M.
const MESH_ROOT = 'z2ma/mesh/';
const MESH_TIMEOUT = 4000;
const meshObject = value => value !== null && typeof value === 'object' && !Array.isArray(value);
export const meshSystemValid = value => typeof value === 'string' && value.length <= 128 && /^[A-Za-z0-9_-]+(?:\/[A-Za-z0-9_-]+)*$/.test(value);
export const meshSystemKey = value => createHash('sha256').update(value).digest('hex');

export class MeshTransport {
    constructor({mqtt, system, runtime, validateAction, clock, uuid = randomUUID}) {
        this.mqtt = mqtt;
        this.system = system;
        this.runtime = runtime;
        this.validateAction = validateAction;
        this.clock = clock ?? {now: () => performance.now(), setTimeout, clearTimeout};
        this.uuid = uuid;
        this.waiters = new Map();
        this.challenges = new Map();
        this.subscribed = false;
        this.closed = false;
    }

    async start() {
        if (!meshSystemValid(this.system) || typeof this.mqtt.subscribe !== 'function'
            || typeof this.mqtt.isConnected !== 'function' || !this.mqtt.isConnected() || this.closed) throw new RuntimeError('mesh_unavailable');
        if (!this.subscribed) {
            let timer;
            try {
                await Promise.race([this.mqtt.subscribe(MESH_ROOT + meshSystemKey(this.system) + '/#'),
                    new Promise((_, reject) => { timer = this.clock.setTimeout(() => reject(new RuntimeError('mesh_unavailable')), MESH_TIMEOUT); })]);
                if (this.closed) {
                    await this.mqtt.unsubscribe?.(MESH_ROOT + meshSystemKey(this.system) + '/#');
                    throw new RuntimeError('mesh_unavailable');
                }
                this.subscribed = true;
            } catch { throw new RuntimeError('mesh_unavailable'); }
            finally { this.clock.clearTimeout(timer); }
        }
    }

    allowed(system) {
        return this.subscribed && this.runtime.active !== null && system !== this.system
            && this.runtime.active.document.mesh_peers?.includes(system);
    }

    cancel() {
        this.challenges.clear();
        for (const waiter of this.waiters.values()) {
            this.clock.clearTimeout(waiter.timer);
            waiter.reject(new RuntimeError('mesh_stopped'));
        }
        this.waiters.clear();
    }

    async stop() {
        this.closed = true;
        this.cancel();
        if (this.subscribed) {
            this.subscribed = false;
            try { await this.mqtt.unsubscribe?.(MESH_ROOT + meshSystemKey(this.system) + '/#'); }
            catch { this.runtime.log('FEHLER', 'Bridge-Abonnement konnte beim Stopp nicht entfernt werden.'); }
        }
    }

    async publish(destination, phase, message) {
        if (!this.mqtt.isConnected()) throw new RuntimeError('mesh_unavailable');
        await this.mqtt.publish('mesh/' + meshSystemKey(destination) + '/' + phase,
            JSON.stringify({mesh_version: 1, source: this.system, ...message}),
            {baseTopic: 'z2ma', skipReceive: false, skipLog: true, clientOptions: {qos: 0, retain: false}});
    }

    exchange(destination, phase, message, responsePhase) {
        if (this.waiters.size >= 16) return Promise.reject(new RuntimeError('mesh_busy'));
        return new Promise((resolve, reject) => {
            const timer = this.clock.setTimeout(() => {
                this.waiters.delete(message.transaction);
                reject(new RuntimeError('mesh_timeout'));
            }, MESH_TIMEOUT);
            timer?.unref?.();
            this.waiters.set(message.transaction, {destination, phase: responsePhase, resolve, reject, timer});
            this.publish(destination, phase, message).catch(() => {
                const waiter = this.waiters.get(message.transaction);
                if (!waiter) return;
                this.waiters.delete(message.transaction);
                this.clock.clearTimeout(timer);
                reject(new RuntimeError('mesh_unavailable'));
            });
        });
    }

    async dispatch(action) {
        const destination = action.target.system;
        if (!this.allowed(destination)) throw new RuntimeError('mesh_not_allowed');
        const generation = this.runtime.generation;
        const local = {...action, target: {...action.target}};
        delete local.target.system;
        delete local.delay_seconds;
        const digest = createHash('sha256').update(JSON.stringify(local)).digest('hex');
        const transaction = this.uuid();
        this.runtime.log('SENDEN', 'Freigabe für eine Aktion im zweiten Z2M-System angefordert.');
        const challenge = await this.exchange(destination, 'prepare', {transaction, digest}, 'challenge');
        if (generation !== this.runtime.generation || !this.allowed(destination)) throw new RuntimeError('mesh_stopped');
        if (challenge.status !== 'ok' || typeof challenge.token !== 'string' || !/^[a-f0-9-]{36}$/.test(challenge.token)) throw new RuntimeError('mesh_rejected');
        const result = await this.exchange(destination, 'execute', {transaction, token: challenge.token, action: local}, 'result');
        if (result.status !== 'ok') throw new RuntimeError('mesh_rejected');
        this.runtime.log('EMPFANG', 'Zielsystem hat die native Befehlsübergabe bestätigt; Gerätewirkung nicht bestätigt.');
    }

    async receive(event) {
        const prefix = MESH_ROOT + meshSystemKey(this.system) + '/';
        if (typeof event?.topic !== 'string' || !event.topic.startsWith(prefix)) return false;
        try {
            if (event.retain === true || typeof event.message !== 'string' || Buffer.byteLength(event.message) > 16384) throw new RuntimeError('mesh_invalid');
            const message = JSON.parse(event.message);
            const phase = event.topic.slice(prefix.length);
            if (!meshObject(message) || message.mesh_version !== 1 || !meshSystemValid(message.source)
                || typeof message.transaction !== 'string' || !/^[a-f0-9-]{36}$/.test(message.transaction)
                || !this.allowed(message.source)) throw new RuntimeError('mesh_not_allowed');
            if (['challenge', 'result'].includes(phase)) {
                const waiter = this.waiters.get(message.transaction);
                if (!waiter || waiter.destination !== message.source || waiter.phase !== phase) return true;
                this.waiters.delete(message.transaction);
                this.clock.clearTimeout(waiter.timer);
                waiter.resolve(message);
                return true;
            }
            const key = message.source + ':' + message.transaction;
            if (phase === 'prepare') {
                if (Object.keys(message).sort().join(',') !== 'digest,mesh_version,source,transaction'
                    || typeof message.digest !== 'string' || !/^[a-f0-9]{64}$/.test(message.digest)) throw new RuntimeError('mesh_invalid');
                for (const [id, entry] of this.challenges) if (entry.expires <= this.clock.now()) this.challenges.delete(id);
                if (this.challenges.has(key) || this.challenges.size >= 64) throw new RuntimeError('mesh_busy');
                const token = this.uuid();
                this.challenges.set(key, {token, digest: message.digest, generation: this.runtime.generation, expires: this.clock.now() + MESH_TIMEOUT});
                await this.publish(message.source, 'challenge', {transaction: message.transaction, status: 'ok', token});
            } else if (phase === 'execute') {
                const challenge = this.challenges.get(key);
                // Consume before any dispatch, including malformed, duplicate or failing commands.
                this.challenges.delete(key);
                if (Object.keys(message).sort().join(',') !== 'action,mesh_version,source,token,transaction'
                    || !challenge || challenge.token !== message.token || challenge.expires <= this.clock.now()
                    || challenge.generation !== this.runtime.generation || !this.validateAction(message.action)
                    || message.action.target.system !== undefined || message.action.delay_seconds !== undefined
                    || createHash('sha256').update(JSON.stringify(message.action)).digest('hex') !== challenge.digest) throw new RuntimeError('mesh_rejected');
                this.runtime.log('EMPFANG', 'Einmalig freigegebene Aktion vom zweiten Z2M-System empfangen.');
                let status = 'ok';
                try { await this.runtime.executeLocal(message.action); }
                catch (error) {
                    status = 'error';
                    this.runtime.lastError = error instanceof RuntimeError ? error.code : 'action_failed';
                    this.runtime.log('FEHLER', 'Empfangene Aktion nicht ausgeführt; keine automatische Wiederholung.', {Grund: this.runtime.lastError});
                }
                await this.publish(message.source, 'result', {transaction: message.transaction, status});
            }
        } catch (error) {
            this.runtime.log('FEHLER', 'Bridge-Nachricht verworfen; keine zusätzliche Geräteaktion.', {Grund: error instanceof RuntimeError ? error.code : 'mesh_invalid'});
        }
        return true;
    }
}
