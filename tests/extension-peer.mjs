import {mkdtemp, mkdir, symlink, writeFile, readFile, rm} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
import {fileURLToPath, pathToFileURL} from 'node:url';
import {createInterface} from 'node:readline';
import {randomUUID} from 'node:crypto';

// Synthetic native MQTT/extension-save boundary. The receiver itself is the real artifact.
const base = 'synthetic/z2m';
const root = await mkdtemp(join(tmpdir(), 'z2ma-peer-'));
const external = join(root, 'data', 'external_extensions');
await mkdir(external, {recursive: true});
await symlink(fileURLToPath(new URL('../node_modules', import.meta.url)), join(external, 'node_modules'), 'junction');
let messages = [];
let listener, stateListener;
let receiver;
let Receiver;
const mqtt = {publish: async (topic, payload, options) => {
    messages.push({topic: base + '/' + topic, payload: JSON.parse(payload), retain: options.clientOptions.retain});
}};
const devices = new Map([
    ['0x0000000000000001', {ID: '0x0000000000000001', ieeeAddr: '0x0000000000000001', name: 'synthetic/button', isDevice: () => true,
        exposes: () => [{type: 'enum', property: 'action', access: 1, values: ['single', 'double']}]}],
    ['0x0000000000000002', {ID: '0x0000000000000002', ieeeAddr: '0x0000000000000002', name: 'synthetic/light', isDevice: () => true,
        exposes: () => [{type: 'binary', property: 'state', access: 7, value_on: 'ON', value_off: 'OFF'}, {type: 'numeric', property: 'temperature', access: 1, unit: '°C'}]}],
    ['0x0000000000000003', {ID: '0x0000000000000003', ieeeAddr: '0x0000000000000003', name: 'synthetic/second-light', isDevice: () => true,
        exposes: () => [{type: 'binary', property: 'state', access: 7, value_on: 'ON', value_off: 'OFF'}]}]
]);
devices.set('0x0000000000000004', {ID: '0x0000000000000004', ieeeAddr: '0x0000000000000004', name: 'synthetic/channel-switch', isDevice: () => true,
    exposes: () => [...[1, 2, 3].map(n => ({type: 'switch', endpoint: 'l' + n, features: [
        {name: 'state', property: 'state_l' + n, endpoint: 'l' + n, type: 'binary', access: 7, value_on: 'ON', value_off: 'OFF'}]})),
    {property: 'level', label: 'Level', type: 'numeric', access: 3, value_min: 0, value_max: 100, value_step: 5},
    {property: 'write_only', label: 'Write only', type: 'numeric', access: 2, value_min: 10, value_max: 50, value_step: 10},
    {property: 'display_flag', label: 'Display flag', type: 'binary', access: 3, value_on: true, value_off: false}]});
const zigbee = {resolveEntity: id => devices.get(id), devicesIterator: () => devices.values(), groupsIterator: () => [{ID: 7, name: 'synthetic/group', isDevice: () => false}].values()};
const bus = {onMQTTMessage: (_owner, callback) => { listener = callback; },
    emitMQTTMessage: event => {
        messages.push({topic: event.topic, payload: JSON.parse(event.message), retain: false});
    },
    onPublishEntityState: (_owner, callback) => { stateListener = callback; },
    removeListeners: () => { listener = undefined; stateListener = undefined; }};
const start = async () => {
    receiver = new Receiver(zigbee, mqtt, {get: () => ({state: 'ON'})}, null, bus, null, null, null,
        {get: () => ({mqtt: {base_topic: base}})}, {error: () => {}});
    await receiver.start();
};
const lines = createInterface({input: process.stdin, crlfDelay: Infinity});
try {
    for await (const line of lines) {
        messages = [];
        const command = JSON.parse(line);
        if (command.control === 'restart') {
            await receiver.stop();
            await start();
        } else if (command.control === 'event') {
            stateListener({entity: devices.get(command.device), payload: command.payload, message: command.payload});
            await Promise.all([...receiver.runtime.tasks]);
        } else if (command.topic === base + '/bridge/request/extension/save') {
            const payload = command.payload;
            const expected = await readFile(new URL('../libs/z2m/z2m-automations.mjs', import.meta.url), 'utf8');
            if (payload.name !== 'z2m-automations.mjs' || payload.code !== expected) throw new Error('Unexpected installation artifact.');
            if (receiver) await receiver.stop();
            const path = join(external, 'z2m-automations.mjs');
            await writeFile(path, payload.code);
            Receiver = (await import(pathToFileURL(path).href + '?instance=' + randomUUID())).default;
            await start();
            messages.push({topic: base + '/bridge/extensions', payload: [{name: payload.name}], retain: true});
            messages.push({topic: base + '/bridge/response/extension/save', payload: {transaction: payload.transaction, status: 'ok', data: {}}, retain: false});
        } else if (listener) {
            await listener({topic: command.topic, message: JSON.stringify(command.payload)});
        }
        process.stdout.write(JSON.stringify(messages) + '\n');
    }
} finally {
    if (receiver) await receiver.stop();
    await rm(root, {recursive: true, force: true});
}
