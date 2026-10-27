<?php

declare(strict_types=1);

require __DIR__ . '/stubs/autoload.php';

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool
{
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$check = static function (bool $value, string $message): void
{
    if (!$value) {
        throw new RuntimeException($message);
    }
};
IPS\Kernel::reset();
$library = json_decode(file_get_contents(dirname(__DIR__) . '/library.json'), true, 512, JSON_THROW_ON_ERROR);
IPS\ModuleLoader::loadSingleModule(dirname(__DIR__) . '/Z2MAutomations', $library['id']);
IPS\ModuleLoader::loadSingleModule(__DIR__ . '/fixtures/MQTTClient', $library['id']);
IPS\ModuleLoader::loadSingleModule(__DIR__ . '/fixtures/MQTTServer', $library['id']);
$metadata = json_decode(file_get_contents(dirname(__DIR__) . '/Z2MAutomations/module.json'), true, 512, JSON_THROW_ON_ERROR);

class ClockedZ2MAutomations extends Z2MAutomations
{
    public int $clock = 1000;
    public array $debugMessages = [];
    public bool $debugFails = false;

    protected function SendDebug(string $Message, string $Data, int $Format): bool
    {
        if ($this->debugFails) {
            throw new RuntimeException('Synthetic debug sink failure');
        }
        $this->debugMessages[] = [$Message, $Data];
        return true;
    }

    protected function Now(): int
    {
        return $this->clock;
    }
}

// Replace only the clock boundary; use the official kernel and data flow.
$registry = new ReflectionProperty(IPS\ModuleLoader::class, 'modules');
$modules = $registry->getValue();
$modules[$metadata['id']]['Class'] = ClockedZ2MAutomations::class;
$registry->setValue(null, $modules);
$id = IPS_CreateInstance($metadata['id']);
IPS_ApplyChanges($id);
$module = IPS\InstanceManager::getInstanceInterface($id);
$check(IPS_GetProperty($id, 'Active') === false, 'New instances must be disabled');
IPS_SetProperty($id, 'Active', true);
IPS_ApplyChanges($id);
$check(IPS_GetInstance($id)['InstanceStatus'] === 201 && $module->CheckHealth() === '', 'Active instance without gateway cannot send');
$parentID = IPS_CreateInstance('{F7A0DD2E-7684-95C0-64C2-D2A9DC47577B}');
IPS_ApplyChanges($parentID);
IPS_ConnectInstance($id, $parentID);
IPS_SetProperty($id, 'Active', true);
IPS_SetProperty($id, 'BaseTopic', 'test/bridge');
IPS_ApplyChanges($id);
$parent = IPS\InstanceManager::getInstanceInterface($parentID);
$state = static fn (): array => json_decode($module->GetTransportState(), true, 512, JSON_THROW_ON_ERROR);
$check($state()['mqttConnected'] && !$state()['bridgeOnline'], 'MQTT and Z2M online states are separate');
$check($module->GetConfigurationForParent() === '{}', 'Shared parent settings must be preserved');
$parent->publish('test/bridge/bridge/state', ['state' => 'online'], true);
$parent->publish('test/bridge/bridge/info', ['version' => '2.test', 'config' => ['advanced' => ['enable_external_js' => true], 'password' => 'synthetic-secret']]);
$parent->publish('test/bridge/bridge/extensions', [['name' => 'z2m-automations.mjs', 'code' => 'synthetic-source']]);
$check($state()['bridgeOnline'] && $state()['version'] === '2.test' && $state()['externalJS'] === true && $state()['extensionInstalled'], 'Normalized bridge cache');
$check(IPS_GetInstance($id)['InstanceStatus'] === 102, 'Bridge online activates instance');
$check(!str_contains($module->GetTransportState(), 'synthetic-secret') && !str_contains($module->GetTransportState(), 'synthetic-source'), 'No raw config or extension code in public state');
$transaction = $module->CheckHealth();
$check($transaction !== '' && $state()['health'] === 'pending', 'Health request starts asynchronously');
$sent = $parent->sent;
$check(count($sent) === 1 && $sent[0]['Topic'] === 'test/bridge/bridge/request/health_check' && $sent[0]['Payload'] === ['transaction' => $transaction], 'Official health topic and transaction');
$check($module->CheckHealth() === $transaction && count($parent->sent) === 1, 'Repeated click must not duplicate pending request');
$parent->publish('test/bridge/bridge/response/health_check', ['transaction' => 'other', 'status' => 'ok', 'data' => ['healthy' => true]]);
$check($state()['health'] === 'pending', 'Wrong transaction ignored');
$parent->publish('test/bridge/bridge/response/health_check', ['transaction' => $transaction, 'status' => 'ok', 'data' => ['healthy' => true]], true);
$check($state()['health'] === 'pending', 'Retained replies cannot complete requests');
$parent->publish('test/bridge/bridge/response/health_check', ['transaction' => $transaction, 'status' => 'ok', 'data' => ['healthy' => true]]);
$check($state()['health'] === 'healthy', 'Matching fresh response completes request');

$transaction = $module->CheckHealth();
$parent->publish('test/bridge/bridge/response/health_check', ['status' => 'ok', 'data' => ['healthy' => true]]);
$parent->publish('test/bridge/bridge/response/health_check', ['transaction' => $transaction, 'status' => 'ok', 'data' => ['healthy' => 'true']]);
$check($state()['health'] === 'pending', 'Missing transaction or invalid healthy value ignored');
$module->clock += 11;
$module->ExpireRequest();
$check($state()['health'] === 'timeout', 'Deadline expires without sleeping');
$parent->publish('test/bridge/bridge/response/health_check', ['transaction' => $transaction, 'status' => 'ok', 'data' => ['healthy' => true]]);
$check($state()['health'] === 'timeout', 'Late response does not replace timeout');
$transaction = $module->CheckHealth();
$parent->publish('test/bridge/bridge/response/health_check', ['transaction' => $transaction, 'status' => 'error', 'error' => 'synthetic-private-detail']);
$check($state()['health'] === 'error' && !str_contains($module->GetTransportState(), 'synthetic-private-detail'), 'Error response is normalized');
$transaction = $module->CheckHealth();
$parent->publish('test/bridge/bridge/response/health_check', ['transaction' => $transaction, 'status' => 'ok', 'data' => ['healthy' => false]]);
$check($state()['health'] === 'unhealthy', 'Negative health result');
$transaction = $module->CheckHealth();
$module->clock += 11;
$parent->publish('test/bridge/bridge/response/health_check', ['transaction' => $transaction, 'status' => 'ok', 'data' => ['healthy' => true]]);
$check($state()['health'] === 'timeout', 'A late response cannot win before the timer callback runs');
$parent->replyImmediately = true;
$check($module->CheckHealth() !== '' && $state()['health'] === 'healthy', 'Synchronous parent response cannot deadlock or be overwritten');
$parent->replyImmediately = false;
$parent->fail = true;
$check($module->CheckHealth() === '' && $state()['health'] === 'error', 'Parent send failure is controlled');
$parent->fail = false;

$before = $state();
$parent->publish('test/bridge-other/bridge/state', ['state' => 'offline']);
$parent->publish('test/bridge/bridge/info', ['version' => []]);
$parent->publish('test/bridge/bridge/extensions', ['invalid']);
$parent->publish('test/bridge/bridge/state', ['state' => 'invalid']);
$check($state() === $before, 'Foreign topics and malformed payloads preserve valid state');
foreach (['not json', '{}', '[]', '{"DataID":"wrong"}', json_encode([
    'DataID' => '{7F7632D9-FA40-4F38-8DEA-C83CD4325A32}', 'PacketType' => 3,
    'Retain' => false, 'Topic' => 'test/bridge/bridge/state', 'Payload' => 'odd'
])] as $invalid) {
    $module->ReceiveData($invalid);
}
$check($state() === $before, 'Malformed envelopes and HEX do not alter state');
$module->ReceiveData(str_repeat('x', 2 * 1048576 + 8193));
$parent->publish('test/bridge/bridge/info', ['version' => str_repeat('x', 129)]);
$check($state() === $before, 'Oversized envelopes and invalid version lengths are ignored');
$module->CheckHealth();
$parent->changeStatus(104);
// Official stubs change status but do not dispatch kernel messages.
$module->MessageSink(time(), $parentID, IM_CHANGESTATUS, [104]);
$check(GetValue(IPS_GetObjectIDByIdent('MQTTConnected', $id)) === false, 'Parent disconnect notification updates visible state immediately');
$check(!$state()['mqttConnected'] && !$state()['bridgeOnline'] && $state()['health'] === 'cancelled', 'Disconnect cancels pending request');
$check($module->CheckHealth() === '', 'No sends while disconnected');
$parent->changeStatus(102);
$module->MessageSink(time(), $parentID, IM_CHANGESTATUS, [102]);
$check($state()['mqttConnected'] && !$state()['bridgeOnline'], 'Reconnect requires new bridge state');
$parent->publish('test/bridge/bridge/state', ['state' => 'online']);
$module->CheckHealth();
IPS_SetProperty($id, 'BaseTopic', 'test/new');
IPS_ApplyChanges($id);
$check(!$state()['bridgeOnline'] && $state()['version'] === '' && !$state()['extensionInstalled'] && $state()['health'] === 'cancelled', 'Topic change invalidates old cache and request');
$parent->publish('test/bridge/bridge/state', ['state' => 'online']);
$check(!$state()['bridgeOnline'], 'Previous topic is no longer accepted');
$parent->publish('test/new/bridge/state', ['state' => 'online']);
$check($state()['bridgeOnline'], 'New topic is accepted');

foreach (['test/a.b', 'test/ÃƒÂ¤', 'test/a"b'] as $validTopic) {
    IPS_SetProperty($id, 'BaseTopic', $validTopic);
    IPS_ApplyChanges($id);
    $parent->publish($validTopic . '/bridge/state', ['state' => 'online']);
    $check($state()['bridgeOnline'], 'Regex and JSON escaping preserve exact topics');
}

foreach (['', 'test/#', 'test/+', 'test/', '/test', 'test//bridge', "test\nbridge"] as $invalidTopic) {
    IPS_SetProperty($id, 'BaseTopic', $invalidTopic);
    IPS_ApplyChanges($id);
    $check(IPS_GetInstance($id)['InstanceStatus'] === 200 && $module->CheckHealth() === '', 'Reject invalid base topic');
}
IPS_SetProperty($id, 'BaseTopic', 'test/new');
foreach ([0, 121] as $invalidTimeout) {
    IPS_SetProperty($id, 'RequestTimeout', $invalidTimeout);
    IPS_ApplyChanges($id);
    $check(IPS_GetInstance($id)['InstanceStatus'] === 200, 'Reject invalid response timeout');
}
IPS_SetProperty($id, 'RequestTimeout', 10);
IPS_ApplyChanges($id);
$serverID = IPS_CreateInstance('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
IPS_ApplyChanges($serverID);
IPS_DisconnectInstance($id);
IPS_ConnectInstance($id, $serverID);
$module->MessageSink(time(), $id, FM_CONNECT, []);
$server = IPS\InstanceManager::getInstanceInterface($serverID);
$check($state()['mqttConnected'] && !$state()['bridgeOnline'], 'Alternative MQTT Server parent supported');
$server->publish('test/new/bridge/state', ['state' => 'online']);
$check($module->CheckHealth() !== '' && count($server->sent) === 1, 'Publish through MQTT Server parent');
$server->publish('test/new/bridge/state', ['state' => 'offline']);
$check(!$state()['bridgeOnline'] && $state()['health'] === 'cancelled', 'Z2M offline cancels pending request');
$server->publish('test/new/bridge/state', ['state' => 'online']);
$module->MessageSink(time(), 0, IPS_KERNELSTARTED, []);
$check(!$state()['bridgeOnline'] && $state()['version'] === '', 'Kernel restart discards stale online state');
IPS_SetProperty($id, 'Active', false);
IPS_ApplyChanges($id);
$check(!$state()['mqttConnected'] && $module->CheckHealth() === '' && IPS_GetInstance($id)['InstanceStatus'] === 104, 'Disabled instance neither sends nor reports online');
$server->publish('test/new/bridge/state', ['state' => 'online']);
$check(!$state()['bridgeOnline'], 'Disabled instance ignores incoming state');

foreach (['test/a.b', 'test/ÃƒÂ¤', 'test/a"b'] as $baseTopic) {
    $filter = Z2MAutomations\Z2MProtocol::ReceiveFilter($baseTopic);
    foreach ([0, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE] as $flags) {
        $packet = json_encode(['Topic' => $baseTopic . '/bridge/state'], $flags);
        $check(preg_match('/' . $filter . '/', $packet) === 1, 'Filter accepts both standard JSON encodings');
    }
    $check(preg_match('/' . $filter . '/', json_encode(['Topic' => $baseTopic . '/bridge/state/extra'])) === 0, 'Topic filter must be exact');
}

IPS_SetProperty($id, 'Active', true);
IPS_ApplyChanges($id);
$secondID = IPS_CreateInstance($metadata['id']);
IPS_ConnectInstance($secondID, $serverID);
IPS_SetProperty($secondID, 'Active', true);
IPS_SetProperty($secondID, 'BaseTopic', 'test/new');
IPS_ApplyChanges($secondID);
$second = IPS\InstanceManager::getInstanceInterface($secondID);
$firstTransaction = $module->CheckHealth();
$secondTransaction = $second->CheckHealth();
$check($firstTransaction !== $secondTransaction, 'Instances use distinct transactions on a shared gateway');
$server->publish('test/new/bridge/response/health_check', ['transaction' => $firstTransaction, 'status' => 'ok', 'data' => ['healthy' => true]]);
$check($state()['health'] === 'healthy' && json_decode($second->GetTransportState(), true)['health'] === 'pending', 'A reply belongs only to its requesting instance');

// A fresh correlated health reply bootstraps online without a retained bridge/state.
$module->MessageSink(time(), 0, IPS_KERNELSTARTED, []);
$check(!$state()['bridgeOnline'], 'Reset has no online evidence');
$module->debugMessages = [];
$transaction = $module->CheckHealth();
$server->publish('test/new/bridge/response/health_check', ['transaction' => $transaction, 'status' => 'ok', 'data' => ['healthy' => true]], true);
$check(!$state()['bridgeOnline'], 'Retained health response cannot bootstrap online');
$server->publish('test/new/bridge/response/health_check', ['transaction' => $transaction, 'status' => 'ok', 'data' => ['healthy' => false]]);
$check($state()['bridgeOnline'] && $state()['health'] === 'unhealthy', 'Reachability and coordinator health are distinct');
$module->ValidateYaml('synthetic-private-yaml');
$module->ReceiveData('synthetic-private-invalid-payload');
$server->publish('test/new/bridge/info', ['version' => 'test', 'config' => ['password' => 'synthetic-private-password']]);
$debug = json_encode($module->debugMessages, JSON_THROW_ON_ERROR);
foreach (['synthetic-private-yaml', 'synthetic-private-invalid-payload', 'synthetic-private-password'] as $secret) {
    $check(!str_contains($debug, $secret), 'Raw input must not enter debug');
}
$stack = [];
foreach ($module->debugMessages as [$category, $data]) {
    $entry = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
    $check(strlen($data) <= 4096 && isset($entry['Instanz'], $entry['Vorgang'], $entry['Aufruf'], $entry['Meldung'], $entry['Bezug']), 'Bounded structured human-readable debug');
    if (str_contains($category, ' | START | ')) {
        $stack[] = [$entry['Vorgang'], $entry['Aufruf']];
    } elseif (str_contains($category, ' | ENDE | ')) {
        $check(array_pop($stack) === [$entry['Vorgang'], $entry['Aufruf']], 'Balanced debug call boundaries');
    }
}
$check($stack === [] && str_contains($debug, 'SENDEN') && str_contains($debug, 'EMPFANG') && str_contains($debug, 'FEHLER'), 'Success, receive and rejection are observable');
$module->debugFails = true;
$transaction = $module->CheckHealth();
$check($transaction !== '', 'Broken debug sink cannot suppress a request');
$server->publish('test/new/bridge/response/health_check', ['transaction' => $transaction, 'status' => 'ok', 'data' => ['healthy' => true]]);
$check($state()['health'] === 'healthy', 'Broken debug sink cannot suppress state processing');
$module->debugFails = false;
$module->debugMessages = [];
$module->MessageSink(time(), 0, IPS_KERNELSTARTED, []);
$server->onPublish = static function (array $packet) use ($server): void
{
    $server->publish('test/new/bridge/response/health_check', ['transaction' => $packet['Payload']['transaction'], 'status' => 'ok', 'data' => ['healthy' => true]]);
};
$module->debugMessages = [];
$module->CheckHealth();
$server->onPublish = null;
$starts = [];
foreach ($module->debugMessages as [$category, $data]) {
    if (str_contains($category, ' | START | ')) {
        $starts[] = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
    }
}
$check(count($starts) === 2 && $starts[0]['Vorgang'] === $starts[1]['Vorgang'] && $starts[0]['Aufruf'] !== $starts[1]['Aufruf'], 'Nested synchronous receive shares operation with a distinct call');
$module->GetTransportState();
$lastStarts = array_values(array_filter($module->debugMessages, static fn (array $entry): bool => str_contains($entry[0], ' | START | ')));
$last = json_decode($lastStarts[array_key_last($lastStarts)][1], true, 512, JSON_THROW_ON_ERROR);
$check($last['Vorgang'] !== $starts[0]['Vorgang'], 'Independent public call gets a new operation');

echo "MQTT integration, correlation, lifecycle and failure checks passed.\n";
