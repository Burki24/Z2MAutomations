<?php

declare(strict_types=1);

require __DIR__ . '/stubs/autoload.php';
$check = static function (bool $value, string $message): void
{ if (!$value) { throw new RuntimeException($message); } };
IPS\Kernel::reset();
$root = dirname(__DIR__);
$library = json_decode(file_get_contents($root . '/library.json'), true, 512, JSON_THROW_ON_ERROR);
IPS\ModuleLoader::loadSingleModule($root . '/Z2MAutomations', $library['id']);
IPS\ModuleLoader::loadSingleModule(__DIR__ . '/fixtures/MQTTClient', $library['id']);
$metadata = json_decode(file_get_contents($root . '/Z2MAutomations/module.json'), true, 512, JSON_THROW_ON_ERROR);
class AutomaticRefreshModule extends Z2MAutomations
{
    public int $clock = 1000;
    public array $debugMessages = [];
    public function RefreshTimerInterval(): int
    {
        return $this->GetTimerInterval('StatusRefresh');
    }
    public function SeedDraft(string $draft): void
    {
        $this->WriteAttributeString('YamlDraft', $draft);
    }
    protected function Now(): int
    {
        return $this->clock;
    }
    protected function LoadConfigurationForm(): array
    {
        return json_decode(file_get_contents(dirname(__DIR__) . '/Z2MAutomations/form.json'), true, 512, JSON_THROW_ON_ERROR);
    }
    protected function SendDebug(string $Message, string $Data, int $Format): bool
    {
        $this->debugMessages[] = [$Message, $Data];
        return true;
    }
}
$registry = new ReflectionProperty(IPS\ModuleLoader::class, 'modules');
$modules = $registry->getValue();
$modules[$metadata['id']]['Class'] = AutomaticRefreshModule::class;
$registry->setValue(null, $modules);
$id = IPS_CreateInstance($metadata['id']);
$module = IPS\InstanceManager::getInstanceInterface($id);
IPS_ApplyChanges($id);
$module->GetConfigurationForm();
$module->RefreshStatus();
$check($module->RefreshTimerInterval() === 0 && IPS_GetInstance($id)['InstanceStatus'] === 104, 'Inactive instances never schedule network requests');
IPS_SetProperty($id, 'Active', true);
IPS_ApplyChanges($id);
$check($module->RefreshTimerInterval() === 0, 'Missing gateway prevents automatic checks');
$parentID = IPS_CreateInstance('{F7A0DD2E-7684-95C0-64C2-D2A9DC47577B}');
IPS_ApplyChanges($parentID);
IPS_ConnectInstance($id, $parentID);
IPS_SetProperty($id, 'BaseTopic', 'synthetic/automatic');
IPS_ApplyChanges($id);
$parent = IPS\InstanceManager::getInstanceInterface($parentID);
$state = static fn (): array => json_decode($module->GetTransportState(), true, 512, JSON_THROW_ON_ERROR);
$ready = ['protocol_version' => 1, 'schema_version' => 1, 'extension_version' => '0.1.0',
    'session'                => '11111111-1111-4111-8111-111111111111', 'state' => 'ready', 'stored_revision' => 0,
    'checksum'               => null, 'automation_count' => 0, 'storage' => 'empty', 'executing' => false,
    'runtime_version'        => 1, 'active_revision' => null, 'active_checksum' => null, 'enabled_count' => 0, 'runtime_error' => ''];
$reply = static function (array $packet, array $data) use ($parent): void
{
    $parent->publish(str_replace('/request/', '/response/', $packet['Topic']), ['protocol_version' => 1, 'transaction' => $packet['Payload']['transaction'], 'status' => 'ok', 'data' => (object) $data]);
};
$responder = static function (array $packet) use ($ready, $reply): void
{
    if (str_ends_with($packet['Topic'], '/request/health_check')) {
        $reply($packet, ['healthy' => true]);
    }
    elseif (str_ends_with($packet['Topic'], '/request/status')) {
        $reply($packet, $ready);
    }
    else {
        throw new RuntimeException('Automatic refresh must never send a write command');
    }
};
$parent->onPublish = $responder;
$module->SeedDraft('synthetic-private-draft');
$check($module->RefreshTimerInterval() === 1000 && count($parent->sent) === 0, 'ApplyChanges schedules one delayed check without synchronous publication');
$module->GetConfigurationForm();
$module->GetConfigurationForm();
$check($module->RefreshTimerInterval() === 1000 && count($parent->sent) === 0, 'Multiple form reads coalesce and do not publish during rendering');
$module->RefreshStatus();
$check(count($parent->sent) === 2 && str_ends_with($parent->sent[0]['Topic'], '/request/health_check') && str_ends_with($parent->sent[1]['Topic'], '/request/status'), 'Health confirmation chains exactly one extension status query');
$check($state()['bridgeOnline'] && $state()['extensionConfirmed'] && IPS_GetInstance($id)['InstanceStatus'] === 102 && $module->RefreshTimerInterval() === 0, 'Fresh replies update the instance banner and stop the timer');
$module->RefreshStatus();
$module->GetConfigurationForm();
$check(count($parent->sent) === 2 && $module->RefreshTimerInterval() === 0, 'Callback repeats and immediate form reloads do not repeat probes');
$module->clock += 11;
$form = json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR);
$check(str_contains(json_encode($form), 'synthetic-private-draft') && $module->RefreshTimerInterval() === 1000, 'Later form opening schedules a fresh check and preserves the local draft');
$module->RefreshStatus();
$check(count($parent->sent) === 4, 'A later form opening performs exactly one new health/status pair');
// A manual operation that starts after scheduling must not be displaced.
$module->clock += 11;
$module->GetConfigurationForm();
$parent->onPublish = null;
$module->RequestExtensionStatus();
$before = count($parent->sent);
$operation = $state()['operation'];
$module->RefreshStatus();
$check(count($parent->sent) === $before && $state()['operation'] === $operation && $state()['lastError'] === '', 'A pending manual operation wins over the automatic timer');
$reply($parent->sent[array_key_last($parent->sent)], $ready);
// Delayed health replies must match and must not be retained.
$module->clock += 11;
$module->GetConfigurationForm();
$module->RefreshStatus();
$health = $parent->sent[array_key_last($parent->sent)];
$before = count($parent->sent);
$parent->publish(str_replace('/request/', '/response/', $health['Topic']), ['transaction' => 'foreign', 'status' => 'ok', 'data' => ['healthy' => true]]);
$parent->publish(str_replace('/request/', '/response/', $health['Topic']), ['transaction' => $health['Payload']['transaction'], 'status' => 'ok', 'data' => ['healthy' => true]], true);
$check(count($parent->sent) === $before && $state()['health'] === 'pending', 'Foreign and retained health responses cannot start a status query');
$reply($health, ['healthy' => false]);
$check(count($parent->sent) === $before && $state()['health'] === 'unhealthy', 'Negative health replies end the attempt without a follow-up');
$module->clock += 11;
$module->GetConfigurationForm();
$module->RefreshStatus();
$health = $parent->sent[array_key_last($parent->sent)];
$before = count($parent->sent);
$module->clock += 11;
$module->ExpireRequest();
$reply($health, ['healthy' => true]);
$module->RefreshStatus();
$check($state()['health'] === 'timeout' && count($parent->sent) === $before && $module->RefreshTimerInterval() === 0, 'Timeouts and late replies do not retry or start follow-ups');
// Starting a manual workflow while health is pending must also win.
$module->clock += 11;
$module->GetConfigurationForm();
$module->RefreshStatus();
$health = $parent->sent[array_key_last($parent->sent)];
$module->RequestExtensionStatus();
$status = $parent->sent[array_key_last($parent->sent)];
$before = count($parent->sent);
$operation = $state()['operation'];
$reply($health, ['healthy' => true]);
$check(count($parent->sent) === $before && $state()['operation'] === $operation, 'Health completion never replaces a manual workflow that started meanwhile');
$reply($status, $ready);
// A changed topic invalidates the scheduled/pending work and its replies.
$module->clock += 11;
$module->GetConfigurationForm();
$module->RefreshStatus();
$oldHealth = $parent->sent[array_key_last($parent->sent)];
IPS_SetProperty($id, 'BaseTopic', 'synthetic/new-automatic');
IPS_ApplyChanges($id);
$before = count($parent->sent);
$reply($oldHealth, ['healthy' => true]);
$check(count($parent->sent) === $before, 'Old-scope health replies cannot continue automatic checks');
$parent->onPublish = $responder;
$module->RefreshStatus();
$check(count($parent->sent) === $before + 2 && str_starts_with($parent->sent[$before]['Topic'], 'synthetic/new-automatic/'), 'New scope gets its own one-shot refresh');
// Disable/disconnect cancels queued probes. Reconnection queues a new attempt.
$module->clock += 11;
$module->GetConfigurationForm();
IPS_SetProperty($id, 'Active', false);
IPS_ApplyChanges($id);
$before = count($parent->sent);
$module->RefreshStatus();
$check(count($parent->sent) === $before && $module->RefreshTimerInterval() === 0, 'Disabling cancels queued probes');
IPS_SetProperty($id, 'Active', true);
IPS_ApplyChanges($id);
$parent->fail = true;
$module->RefreshStatus();
$check($state()['health'] === 'error' && $module->RefreshTimerInterval() === 0, 'A transport failure terminates the automatic attempt');
$parent->fail = false;
$parent->changeStatus(104);
$module->MessageSink(time(), $parentID, IM_CHANGESTATUS, [104]);
$parent->changeStatus(102);
$module->MessageSink(time(), $parentID, IM_CHANGESTATUS, [102]);
$check($module->RefreshTimerInterval() === 1000, 'Gateway recovery queues one new attempt');
$module->RefreshStatus();
$check($state()['bridgeOnline'] && $state()['extensionConfirmed'], 'Gateway recovery refreshes real status without a manual button');
// A missing extension response is bounded by the existing workflow timeout.
$module->clock += 11;
$parent->onPublish = static function (array $packet) use ($reply): void
{
    if (str_ends_with($packet['Topic'], '/request/health_check')) {
        $reply($packet, ['healthy' => true]);
    }
};
$module->GetConfigurationForm();
$module->RefreshStatus();
$before = count($parent->sent);
$module->clock += 11;
$module->ExpireRequest();
$module->RefreshStatus();
$check($state()['operation']['status'] === 'timeout' && count($parent->sent) === $before && $module->RefreshTimerInterval() === 0, 'An unanswered extension query expires without automatic retries');
$debug = json_encode($module->debugMessages, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
$check(!str_contains($debug, 'synthetic-private-draft') && str_contains($debug, 'Automatische Health-Prüfung bestätigt'), 'Automatic diagnostics explain the flow without exposing draft content');
echo "Automatic status refresh checks passed.\n";
