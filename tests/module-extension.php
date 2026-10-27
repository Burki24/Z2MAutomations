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
$check = static function (bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
IPS\Kernel::reset();
$root = dirname(__DIR__);
$library = json_decode(file_get_contents($root . '/library.json'), true, 512, JSON_THROW_ON_ERROR);
IPS\ModuleLoader::loadSingleModule($root . '/Z2MAutomations', $library['id']);
IPS\ModuleLoader::loadSingleModule(__DIR__ . '/fixtures/MQTTClient', $library['id']);
$metadata = json_decode(file_get_contents($root . '/Z2MAutomations/module.json'), true, 512, JSON_THROW_ON_ERROR);
$id = IPS_CreateInstance($metadata['id']);
$parentID = IPS_CreateInstance('{F7A0DD2E-7684-95C0-64C2-D2A9DC47577B}');
IPS_ApplyChanges($parentID);
IPS_ConnectInstance($id, $parentID);
IPS_SetProperty($id, 'Active', true);
IPS_SetProperty($id, 'BaseTopic', 'synthetic/z2m');
IPS_ApplyChanges($id);
$module = IPS\InstanceManager::getInstanceInterface($id);
$parent = IPS\InstanceManager::getInstanceInterface($parentID);
$parent->publish('synthetic/z2m/bridge/state', ['state' => 'online']);
$state = static fn (): array => json_decode($module->GetTransportState(), true, 512, JSON_THROW_ON_ERROR);
$check($module->InstallExtension() === '' && $state()['lastError'] === 'external_js_disabled', 'Never enable external JavaScript automatically');
$parent->publish('synthetic/z2m/bridge/info', ['version' => '2.14.2', 'config' => ['advanced' => ['enable_external_js' => true]]]);
$peer = proc_open(['node', __DIR__ . '/extension-peer.mjs'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, $root);
if (!is_resource($peer)) {
    throw new RuntimeException('Cannot start synthetic extension peer.');
}
stream_set_timeout($pipes[1], 10);
$exchange = static function (array $command) use ($pipes): array
{
    fwrite($pipes[0], json_encode($command, JSON_THROW_ON_ERROR) . "\n");
    fflush($pipes[0]);
    $line = fgets($pipes[1]);
    if ($line === false) {
        throw new RuntimeException('Synthetic extension peer did not respond.');
    }
    return json_decode($line, false, 512, JSON_THROW_ON_ERROR);
};
$deliver = static function (array $messages) use ($parent): void
{
    foreach ($messages as $message) {
        // Preserve JSON objects in native save responses, including the empty data object.
        $parent->publish($message->topic, $message->payload, $message->retain);
    }
};
$parent->onPublish = static function (array $packet) use ($exchange, $deliver): void
{
    $deliver($exchange(['topic' => $packet['Topic'], 'payload' => $packet['Payload']]));
};
try {
    $install = $module->InstallExtension();
    $check($install !== '' && $state()['operation']['status'] === 'installed' && $state()['extensionConfirmed'], 'Installation completes only after a correlated receiver probe');
    $sent = $parent->sent;
    $packet = $sent[0];
    $check($packet['Topic'] === 'synthetic/z2m/bridge/request/extension/save'
        && $packet['Payload']['name'] === 'z2m-automations.mjs'
        && $packet['Payload']['code'] === file_get_contents($root . '/libs/z2m/z2m-automations.mjs'), 'Exact bundled artifact on official topic');
    $check(GetValue(IPS_GetObjectIDByIdent('ExtensionReady', $id)) === true, 'Confirmed readiness is visible');
    $yaml = "schema_version: 1\nrevision: 1\nautomations: []\n";
    $check($module->StoreYaml($yaml) === '' && $state()['lastError'] === 'not_validated', 'Never store an unvalidated draft');
    $check($module->ValidateYaml($yaml) !== '' && $state()['operation']['status'] === 'validated', 'Real JS receiver validates the module draft');
    $check($state()['validation']['checksum'] === hash('sha256', $yaml), 'Validation receipt is tied to exact draft bytes');
    $beforeForm = count($parent->sent);
    $form = $module->GetConfigurationForm();
    $check(count($parent->sent) === $beforeForm, 'Opening the configuration form never sends MQTT requests');
    $check(str_contains($form, hash('sha256', $yaml)) && !str_contains($module->GetTransportState(), 'automations: []'), 'Preview exposes checksum without leaking YAML through state API');
    $check($module->StoreYaml($yaml) !== '' && $state()['operation']['status'] === 'stored', 'Real receiver persists the module draft');
    $check($state()['extension']['storedRevision'] === 1 && GetValue(IPS_GetObjectIDByIdent('StoredRevision', $id)) === 1, 'Store acknowledgement updates revision');
    $check($module->ValidateYaml('schema_version: [') !== '' && $state()['lastError'] === 'invalid_yaml', 'Real parser errors reach fixed module messages');
    $check($state()['extension']['storedRevision'] === 1, 'Failed validation does not change stored revision');
    $second = str_replace('revision: 1', 'revision: 2', $yaml);
    $module->ValidateYaml($second);
    $check($module->StoreYaml($second . '# edited') === '' && $state()['lastError'] === 'not_validated', 'Editing a validated draft revokes approval');
    $module->ValidateYaml($second);
    $deliver($exchange(['control' => 'restart']));
    $check(!$state()['extensionConfirmed'] && $state()['validation'] === null, 'Receiver restart revokes confirmation and validation');
    $module->ValidateYaml($second);
    $check($module->StoreYaml($second) !== '' && $state()['extension']['storedRevision'] === 2, 'Revalidate and store after session restart');
    $module->ValidateYaml($second);
    $check($module->StoreYaml($second) !== '' && $state()['operation']['status'] === 'stored'
        && $state()['extension']['storedRevision'] === 2, 'Module supports an explicitly revalidated idempotent store');
    $draft = json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR);
    $fields = array_column($draft['actions'], null, 'name');
    $check($fields['YamlInput']['value'] === $second && isset($fields['ValidationInfo']), 'Last submitted draft survives form reopen');
    $check(!empty($fields['InstallButton']['confirm']) && !empty($fields['StoreButton']['confirm']), 'Mutating form actions require explicit confirmation');
    $check($module->RequestExtensionStatus() !== '' && $state()['operation']['status'] === 'status_received', 'Explicit receiver refresh works');
} finally {
    fclose($pipes[0]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exit = proc_close($peer);
    $check($exit === 0, 'Synthetic peer must shut down cleanly: ' . $stderr);
}

// Deterministic negative protocol scenarios use the native stub boundary only.
$parent->onPublish = null;
class ClockedExtensionModule extends Z2MAutomations
{
    public int $clock = 1000;

    protected function Now(): int
    {
        return $this->clock;
    }
}
$registry = new ReflectionProperty(IPS\ModuleLoader::class, 'modules');
$modules = $registry->getValue();
$modules[$metadata['id']]['Class'] = ClockedExtensionModule::class;
$registry->setValue(null, $modules);
$controlledID = IPS_CreateInstance($metadata['id']);
IPS_ConnectInstance($controlledID, $parentID);
IPS_SetProperty($controlledID, 'Active', true);
IPS_SetProperty($controlledID, 'BaseTopic', 'synthetic/z2m');
IPS_ApplyChanges($controlledID);
$controlled = IPS\InstanceManager::getInstanceInterface($controlledID);
$controlledState = static fn (): array => json_decode($controlled->GetTransportState(), true, 512, JSON_THROW_ON_ERROR);
$parent->publish('synthetic/z2m/bridge/state', ['state' => 'online']);
$parent->publish('synthetic/z2m/bridge/info', ['version' => '2.14.2', 'config' => ['advanced' => ['enable_external_js' => true]]]);
$last = static function () use ($parent): array
{
    $sent = $parent->sent;
    return end($sent);
};
$answer = static function (array $body, bool $retained = false, ?string $transaction = null) use ($parent, $last): void
{
    $request = $last();
    $parent->publish(
        str_replace('/request/', '/response/', $request['Topic']),
        ['transaction' => $transaction ?? $request['Payload']['transaction']] + $body,
        $retained
    );
};
$source = "schema_version: 1\nrevision: 3\nautomations: []\n";
$remote = ['protocol_version' => 1, 'schema_version' => 1, 'extension_version' => '0.1.0',
    'session'                 => '11111111-1111-4111-8111-111111111111', 'state' => 'ready', 'stored_revision' => 2,
    'checksum'                => hash('sha256', str_replace('revision: 3', 'revision: 2', $source)),
    'automation_count'        => 0, 'storage' => 'ok', 'executing' => false];
$runningRemote = (object) ($remote + ['runtime_version' => 1, 'active_revision' => 2]);
$runningRemote->executing = true;
$check(Z2MAutomations\ExtensionContract::Status($runningRemote)['executing'] === true, 'Running receiver status remains compatible with Symcon normalization');
$runningRemote->executing = 'true';
$check(Z2MAutomations\ExtensionContract::Status($runningRemote) === null, 'Execution state must be a real boolean');
$replyStatus = static function (array $status) use ($answer): void
{
    $answer(['protocol_version' => 1, 'status' => 'ok', 'data' => (object) $status]);
};
$validate = static function () use ($controlled, $source, $replyStatus, $remote, $answer, $check, $controlledState): void
{
    $check($controlled->ValidateYaml($source) !== '', 'Start validation workflow');
    $replyStatus($remote);
    $answer(['protocol_version' => 1, 'status' => 'ok', 'data' => (object) ['valid' => true, 'revision' => 3,
        'checksum'                                                                  => hash('sha256', $source), 'automation_count' => 0, 'executing' => false]]);
    $check($controlledState()['operation']['status'] === 'validated', 'Obtain current validation receipt');
};

$install = $controlled->InstallExtension();
$count = count($parent->sent);
$check($controlled->InstallExtension() === $install && count($parent->sent) === $count, 'Duplicate install click does not upload twice');
$answer(['status' => 'ok', 'data' => (object) []], false, 'foreign-transaction');
$check($controlledState()['operation']['status'] === 'pending', 'Wrong installation transaction is ignored');
$answer(['status' => 'ok', 'data' => (object) []], true);
$check($controlledState()['operation']['status'] === 'pending', 'Retained installation response cannot authorize a probe');
$answer(['status' => 'error', 'error' => 'synthetic-sensitive-install-error', 'data' => (object) []]);
$check($controlledState()['lastError'] === 'installation_failed' && !str_contains($controlled->GetTransportState(), 'synthetic-sensitive'), 'Native installation errors are sanitized');

$controlled->InstallExtension();
$answer(['status' => 'ok', 'data' => (object) []]);
$check(str_ends_with($last()['Topic'], '/request/status') && !$controlledState()['extensionConfirmed'], 'Upload acknowledgement alone is not receiver confirmation');
$controlled->clock += 11;
$controlled->ExpireRequest();
$check($controlledState()['operation']['status'] === 'timeout' && !$controlledState()['extensionConfirmed'], 'Missing receiver confirmation expires');
$replyStatus($remote);
$check($controlledState()['operation']['status'] === 'timeout', 'Late status response cannot complete a timed out installation');

$parent->publish('synthetic/z2m/symcon/automations/status', (object) $remote, true);
$check(!$controlledState()['extensionConfirmed'], 'Retained status is observational only');
$controlled->RequestExtensionStatus();
$replyStatus($remote);
$check($controlledState()['extensionConfirmed'], 'Only a fresh correlated status confirms receiver readiness');
$validate();
$controlled->StoreYaml($source);
$before = count($parent->sent);
$replyStatus(array_replace($remote, ['stored_revision' => 3, 'checksum' => hash('sha256', $source)]));
$check($controlledState()['lastError'] === 'revision_conflict' && count($parent->sent) === $before, 'Revision conflict prevents the store publish');

$validate();
$controlled->StoreYaml($source);
$replyStatus(array_replace($remote, ['session' => '22222222-2222-4222-8222-222222222222']));
$check($controlledState()['lastError'] === 'session_mismatch', 'New session invalidates prior YAML approval');

$controlled->ValidateYaml($source);
$replyStatus($remote);
$answer(['protocol_version' => 1, 'status' => 'ok', 'data' => (object) ['valid' => true, 'revision' => 3,
    'checksum'                                                                  => str_repeat('0', 64), 'automation_count' => 0, 'executing' => false]]);
$check($controlledState()['lastError'] === 'invalid_response' && $controlledState()['validation'] === null, 'Wrong validation checksum is never accepted');

$controlled->ValidateYaml($source);
$replyStatus($remote);
$answer(['protocol_version' => 1, 'status' => 'error', 'error' => (object) ['code' => 'untrusted-secret-code', 'message' => 'synthetic-private-detail']]);
$check($controlledState()['lastError'] === 'extension_error' && !str_contains($controlled->GetTransportState(), 'synthetic-private'), 'Unknown extension error messages are not copied');

$validate();
$controlled->StoreYaml($source);
$replyStatus($remote);
$check(str_ends_with($last()['Topic'], '/request/store') && $last()['Payload']['session'] === $remote['session']
    && $last()['Payload']['expected_revision'] === 2, 'Store uses exactly the freshly probed session and revision');
$answer(['protocol_version' => 1, 'status' => 'ok', 'data' => (object) ['stored_revision' => 3,
    'checksum'                                                                            => str_repeat('0', 64), 'automation_count' => 0, 'storage' => 'ok', 'executing' => false, 'unchanged' => false]]);
$check($controlledState()['lastError'] === 'invalid_response' && $controlledState()['operation']['status'] === 'error', 'Invalid store acknowledgement never reports stored');

$validate();
$controlled->StoreYaml($source);
$before = count($parent->sent);
$replyStatus(array_replace($remote, ['storage' => 'recovered_read_only']));
$check($controlledState()['lastError'] === 'storage_unavailable' && count($parent->sent) === $before, 'Read-only recovery blocks store before publishing YAML');

$pending = $controlled->RequestExtensionStatus();
$before = count($parent->sent);
$check($controlled->StoreYaml($source) === '' && $controlledState()['lastError'] === 'busy'
    && $controlledState()['operation']['transaction'] === $pending && count($parent->sent) === $before, 'A busy operation cannot be overwritten by another action');
$controlled->CheckHealth();
$controlled->clock += 11;
$controlled->ExpireRequest();
$check($controlledState()['operation']['status'] === 'timeout' && $controlledState()['health'] === 'timeout', 'One timer expires both independent request channels');

$before = count($parent->sent);
$check($controlled->ValidateYaml(str_repeat('x', 131073)) === '' && count($parent->sent) === $before, 'Oversized YAML is rejected before network access');
$check($controlled->ValidateYaml("\xff") === '' && $controlledState()['lastError'] === 'invalid_yaml', 'Malformed UTF-8 is rejected locally');
$parent->fail = true;
$check($controlled->RequestExtensionStatus() === '' && $controlledState()['lastError'] === 'send_failed', 'Parent publish failure closes the operation');
$parent->fail = false;
$controlled->RequestExtensionStatus();
$replyStatus($remote);
$check($controlledState()['extensionConfirmed'], 'Start protocol failure check with a confirmed receiver');
$controlled->RequestExtensionStatus();
$answer(['protocol_version' => 2, 'status' => 'ok', 'data' => (object) $remote]);
$check($controlledState()['lastError'] === 'protocol_mismatch' && !$controlledState()['extensionConfirmed'], 'Wrong response protocol revokes earlier readiness');

$validate();
$controlled->StoreYaml($source);
$parent->publish('synthetic/z2m/bridge/state', ['state' => 'offline']);
$check($controlledState()['operation']['status'] === 'cancelled' && !$controlledState()['extensionConfirmed'], 'Bridge offline cancels mutation workflow');
$parent->publish('synthetic/z2m/bridge/state', ['state' => 'online']);
$validate();
IPS_SetProperty($controlledID, 'BaseTopic', 'synthetic/other');
IPS_ApplyChanges($controlledID);
$check($controlledState()['validation'] === null && !$controlledState()['extensionConfirmed'], 'Changing target revokes approval');

echo "PHP-to-JavaScript installation, validation and persistence integration passed.\n";
