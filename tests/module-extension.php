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
class ObservedAutomationModule extends Z2MAutomations
{
    public array $debugMessages = [];
    public bool $debugFails = false;

    protected function UpdateFormField(string $Field, string $Parameter, mixed $Value): bool
    {
        if (in_array($Parameter, ['options', 'values'], true)) {
            if (!is_string($Value) || !is_array(json_decode($Value, true, 512, JSON_THROW_ON_ERROR))) {
                throw new RuntimeException('Complex live form updates must be JSON encoded.');
            }
        }
        return true;
    }

    // The test subclass lives outside the module directory; keep the real form as its fixture.
    protected function LoadConfigurationForm(): array
    {
        return json_decode(file_get_contents(dirname(__DIR__) . '/Z2MAutomations/form.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    protected function SendDebug(string $Message, string $Data, int $Format): bool
    {
        if ($this->debugFails) {
            throw new RuntimeException('Synthetic debug sink failure');
        }
        $this->debugMessages[] = [$Message, $Data];
        return true;
    }
}
$initialRegistry = new ReflectionProperty(IPS\ModuleLoader::class, 'modules');
$initialModules = $initialRegistry->getValue();
$initialModules[$metadata['id']]['Class'] = ObservedAutomationModule::class;
$initialRegistry->setValue(null, $initialModules);
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
    $check($module->LoadAutomationEditor() !== '' && $state()['operation']['status'] === 'editor_loaded', 'Editor loads real receiver catalog and stored configuration');
    $fields = array_column(json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR)['actions'], null, 'name');
    $check(count($fields['EditorSource']['options']) >= 2 && count($fields['EditorTarget']['options']) >= 2, 'Real Exposes populate device selectors');
    $module->SelectEditorDevice('Source', '0x0000000000000001');
    $module->SelectEditorDevice('Target', '0x0000000000000002');
    $fields = array_column(json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR)['actions'], null, 'name');
    $trigger = $fields['EditorSourceChoice']['options'][1]['value'];
    $action = $fields['EditorTargetChoice']['options'][1]['value'];
    $check(!$module->CreateSimpleAutomation('Invalid selection', '0x0000000000000001', 'forged-choice', '0x0000000000000002', $action)
        && $state()['lastError'] === 'editor_invalid', 'The builder rejects arbitrary properties or values outside the offered catalog');
    $check($module->CreateSimpleAutomation('Synthetic click automation', '0x0000000000000001', $trigger, '0x0000000000000002', $action), 'Automation created only from offered choices, without handwritten YAML');
    $fields = array_column(json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR)['actions'], null, 'name');
    $createdYaml = $fields['YamlInput']['value'];
    $created = json_decode($createdYaml, true, 512, JSON_THROW_ON_ERROR);
    $check($created['revision'] === 3 && count($created['automations']) === 1 && $fields['YamlInput']['visible'] === false, 'Advanced YAML remains hidden while draft is generated');
    $check($module->ValidateAutomationDraft() !== '' && $state()['operation']['status'] === 'validated', 'Generated JSON/YAML validates without exposing or reading a hidden form field');
    $check($module->StoreAutomationDraft() !== '' && $state()['operation']['status'] === 'stored', 'Generated rule persists without handwritten YAML');
    $check($module->ActivateAutomations() !== '' && $state()['operation']['status'] === 'activated', 'Activation waits for native command acknowledgement and an additional fresh status');
    $check(GetValue(IPS_GetObjectIDByIdent('ExecutionActive', $id)) && GetValue(IPS_GetObjectIDByIdent('ActiveRevision', $id)) === 3
        && GetValue(IPS_GetObjectIDByIdent('EnabledAutomations', $id)) === 1, 'Confirmed execution has visible status variables');
    IPS_DisconnectInstance($id);
    $messages = $exchange(['control' => 'event', 'device' => '0x0000000000000001', 'payload' => ['action' => 'single']]);
    $commands = array_values(array_filter($messages, static fn (object $message): bool => $message->topic === 'synthetic/z2m/synthetic/light/set'));
    $check(count($commands) === 1 && $commands[0]->payload->state === 'ON', 'Z2M executes the stored rule after Symcon data flow is disconnected');
    IPS_ConnectInstance($id, $parentID);
    $parent->publish('synthetic/z2m/bridge/state', ['state' => 'online']);
    $check($module->StopAutomations() !== '' && $state()['operation']['status'] === 'stopped'
        && !GetValue(IPS_GetObjectIDByIdent('ExecutionActive', $id)), 'Stop is confirmed through the real receiver');
    $module->LoadAutomationEditor();
    $module->SelectEditorDevice('Source', '0x0000000000000001');
    $module->SelectEditorDevice('Target', '0x0000000000000002');
    $check($module->CreateSimpleAutomation('Synthetic click automation', '0x0000000000000001', $trigger, '0x0000000000000002', $action, '', false, false), 'A second rule may share a name and be created disabled');
    $fields = array_column(json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR)['actions'], null, 'name');
    $extended = json_decode($fields['YamlInput']['value'], true, 512, JSON_THROW_ON_ERROR);
    $check(count($extended['automations']) === 2 && $extended['automations'][0] === $created['automations'][0]
        && !$extended['automations'][1]['enabled'], 'Adding a disabled rule preserves the complete existing automation');
    $ruleID = $extended['automations'][0]['id'];
    $sentBeforeEditing = count($parent->sent);
    $check($module->SelectAutomation($ruleID), 'Existing rule can be selected by stable ID');
    $fields = array_column(json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR)['actions'], null, 'name');
    $token = $fields['EditorEditToken']['value'];
    $check($fields['EditorRule']['options'][1]['value'] !== $fields['EditorRule']['options'][2]['value']
        && $fields['EditorRule']['options'][1]['caption'] !== $fields['EditorRule']['options'][2]['caption'], 'Duplicate names remain distinguishable and use different stable IDs');
    $changedTrigger = $fields['EditorSourceChoice']['options'][2]['value'];
    $changedAction = $fields['EditorTargetChoice']['options'][2]['value'];
    $check($fields['EditorName']['value'] === $created['automations'][0]['name']
        && $fields['EditorSourceChoice']['value'] === $trigger && $fields['EditorTargetChoice']['value'] === $action, 'Selecting an existing rule restores all simple fields');
    $check($module->UpdateAutomation($ruleID, $token, 'Renamed synthetic rule', false, '0x0000000000000001', $changedTrigger, '0x0000000000000002', $changedAction), 'Existing rule can be edited and disabled locally');
    $fields = array_column(json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR)['actions'], null, 'name');
    $edited = json_decode($fields['YamlInput']['value'], true, 512, JSON_THROW_ON_ERROR);
    $check($edited['automations'][0]['id'] === $ruleID && $edited['automations'][0]['name'] === 'Renamed synthetic rule'
        && !$edited['automations'][0]['enabled'] && $edited['automations'][1] === $extended['automations'][1]
        && $edited['automations'][0]['triggers'][0]['value'] === 'double' && $edited['automations'][0]['actions'][0]['values'] === ['state' => 'OFF']
        && $edited['revision'] === 4 && count($parent->sent) === $sentBeforeEditing, 'Editing preserves identity, unrelated rules and remote state');
    $check(!$module->DeleteAutomation($ruleID, $token) && $state()['lastError'] === 'editor_changed', 'Old selection tokens cannot delete a changed draft');
    $check($module->ValidateAutomationDraft() !== '' && $module->StoreAutomationDraft() !== '', 'Edited rule validates and stores through the real receiver');
    $module->LoadAutomationEditor();
    foreach ([$ruleID, $extended['automations'][1]['id']] as $deleteID) {
        $check($module->SelectAutomation($deleteID), 'Select the precise rule to delete');
        $fields = array_column(json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR)['actions'], null, 'name');
        $check($module->DeleteAutomation($deleteID, $fields['EditorEditToken']['value']), 'Delete selected rule from local draft');
        if ($deleteID === $ruleID) {
            $remainingForm = array_column(json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR)['actions'], null, 'name');
            $check(json_decode($remainingForm['YamlInput']['value'], true, 512, JSON_THROW_ON_ERROR)['automations'] === [$extended['automations'][1]], 'Deleting one rule preserves the exact other rule');
        }
    }
    $fields = array_column(json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR)['actions'], null, 'name');
    $check(json_decode($fields['YamlInput']['value'], true, 512, JSON_THROW_ON_ERROR)['automations'] === []
        && $state()['extension']['storedRevision'] === 4, 'Deleting the last rule creates an empty local draft without touching Z2M');
    $check($module->ValidateAutomationDraft() !== '' && $module->StoreAutomationDraft() !== '', 'Deleting all rules persists as a valid empty configuration');
    $module->LoadAutomationEditor();
    $advanced = $created['automations'][0];
    $advanced['triggers'][0]['for_seconds'] = 5;
    $advanced['actions'][0]['delay_seconds'] = 2;
    $advanced['conditions'] = [['type' => 'device_value', 'device' => '0x0000000000000002', 'property' => 'state', 'operator' => 'eq', 'value' => 'OFF']];
    $advancedYaml = json_encode(['schema_version' => 1, 'revision' => 6, 'automations' => [$advanced]], JSON_THROW_ON_ERROR);
    $module->ValidateYaml($advancedYaml);
    $check($module->StoreYaml($advancedYaml) !== '', 'Prepare a real stored advanced rule');
    $module->LoadAutomationEditor();
    $check($module->SelectAutomation($ruleID), 'Advanced rules remain selectable');
    $fields = array_column(json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR)['actions'], null, 'name');
    $token = $fields['EditorEditToken']['value'];
    $check(!$fields['EditorSource']['enabled'] && $fields['EditorName']['value'] === $advanced['name'], 'Advanced rules expose metadata editing without flattening their clauses');
    $check(!$module->DeleteAutomation($ruleID, $token, '', true) && $state()['lastError'] === 'editor_changed', 'An empty unsaved expert draft cannot be overwritten');
    $check(!$module->SelectAutomation('unknown-rule') && $state()['lastError'] === 'editor_missing', 'Unknown IDs never fall back to a name or row');
    $check(!$module->UpdateAutomation($ruleID, $token, 'Unsafe simplification', true, '0x0000000000000001', $trigger, '0x0000000000000002', $action)
        && $state()['lastError'] === 'editor_advanced', 'Advanced clauses cannot be silently replaced with simple fields');
    $check($module->UpdateAutomation($ruleID, $token, 'Advanced renamed', false, '', '', '', ''), 'Advanced rules allow safe name and enabled state changes');
    $fields = array_column(json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR)['actions'], null, 'name');
    $advancedEdited = json_decode($fields['YamlInput']['value'], true, 512, JSON_THROW_ON_ERROR)['automations'][0];
    $expectedAdvanced = $advanced;
    $expectedAdvanced['name'] = 'Advanced renamed';
    $expectedAdvanced['enabled'] = false;
    $check($advancedEdited === $expectedAdvanced, 'Metadata edits preserve every advanced condition, trigger and action');
    $token = $fields['EditorEditToken']['value'];
    $module->SelectAutomation('');
    $check(!$module->DeleteAutomation($ruleID, $token), 'Switching to a new rule invalidates the previous selection');
    $module->SelectAutomation($ruleID);
    $fields = array_column(json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR)['actions'], null, 'name');
    $firstWindowToken = $fields['EditorEditToken']['value'];
    $module->SelectAutomation($ruleID);
    $check(!$module->DeleteAutomation($ruleID, $firstWindowToken), 'A second form selection invalidates the first form token even for the same ID');
    $check($module->ValidateAutomationDraft() !== '' && $module->StoreAutomationDraft() !== '', 'Advanced metadata edits persist through the authoritative receiver');
    $check(!$module->DeleteAutomation($ruleID, $firstWindowToken), 'Remote revision changes invalidate editor selections');
    $module->LoadAutomationEditor();
    $fields = array_column(json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR)['actions'], null, 'name');
    $module->ValidateYaml($fields['YamlInput']['value'] . '# edited');
    $check(!$module->CreateSimpleAutomation('Refuse stale editor draft', '0x0000000000000001', $trigger, '0x0000000000000002', $action)
        && $state()['lastError'] === 'editor_changed', 'Manual edits are not silently overwritten by the builder');
    $module->debugFails = true;
    $check($module->ActivateAutomations() !== '' && $state()['operation']['status'] === 'activated', 'A failing diagnostic channel cannot suppress activation');
    $check($module->StopAutomations() !== '' && $state()['operation']['status'] === 'stopped', 'A failing diagnostic channel cannot suppress stop');
    $module->debugFails = false;
    $stack = [];
    $captured = json_encode($module->debugMessages, JSON_THROW_ON_ERROR);
    $check(!str_contains($captured, 'Synthetic click automation') && !str_contains($captured, 'activation_token')
        && !str_contains($captured, $firstWindowToken) && !str_contains($captured, 'automations: []'), 'Debug must not include draft bodies, names or selection/activation tokens');
    foreach ($module->debugMessages as [$category, $body]) {
        $entry = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        $check(strlen($body) <= 4096 && isset($entry['Instanz'], $entry['Vorgang'], $entry['Aufruf'], $entry['Meldung'], $entry['Bezug']), 'New actions use the central structured debug contract');
        if (str_contains($category, ' | START | ')) {
            $stack[] = [$entry['Vorgang'], $entry['Aufruf']];
        } elseif (str_contains($category, ' | ENDE | ')) {
            $check(array_pop($stack) === [$entry['Vorgang'], $entry['Aufruf']], 'Nested action/debug scopes are balanced');
        }
    }
    $check($stack === [] && str_contains($captured, 'ActivateAutomations') && str_contains($captured, 'StopAutomations')
        && str_contains($captured, 'CreateSimpleAutomation') && str_contains($captured, 'FEHLER'), 'Activation, stop, editor and rejection all remain human-observable');
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
$runningRemote = (object) ($remote + ['runtime_version' => 1, 'active_revision' => 2, 'active_checksum' => $remote['checksum'], 'enabled_count' => 0, 'runtime_error' => '']);
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

// Activation acknowledgements and the final verification must both match the probed revision/session.
$parent->publish('synthetic/other/bridge/state', ['state' => 'online']);
$inactive = $remote + ['runtime_version' => 1, 'active_revision' => null, 'active_checksum' => null, 'enabled_count' => 0, 'runtime_error' => ''];
$active = $inactive;
$active['executing'] = true;
$active['active_revision'] = $active['stored_revision'];
$active['active_checksum'] = $active['checksum'];
$token = '33333333-3333-4333-8333-333333333333';
$probeActivation = static function () use ($controlled, $replyStatus, $inactive, $token, $last, $controlledState, $check): void
{
    $check($controlled->ActivateAutomations() !== '', 'Start activation with a fresh status request');
    $replyStatus($inactive + ['activation_token' => $token]);
    $packet = $last();
    $check(str_ends_with($packet['Topic'], '/request/activate') && $packet['Payload']['activation_token'] === $token
        && $packet['Payload']['expected_revision'] === $inactive['stored_revision'] && $packet['Payload']['checksum'] === $inactive['checksum'], 'Activation sends only the exact fresh snapshot');
    $check(!$controlledState()['runtimeConfirmed'] && !str_contains(json_encode($controlledState()), $token), 'Sending is not activation confirmation and tokens never enter exported state');
};
$controlled->ActivateAutomations();
$replyStatus($remote);
$check($controlledState()['lastError'] === 'runtime_unavailable', 'Legacy receivers cannot silently authorize activation');
$probeActivation();
$wrongRevision = $active;
$wrongRevision['stored_revision']++;
$wrongRevision['active_revision']++;
$answer(['protocol_version' => 1, 'status' => 'ok', 'data' => (object) $wrongRevision]);
$check($controlledState()['lastError'] === 'runtime_mismatch' && !$controlledState()['runtimeConfirmed'], 'Wrong active revision is never shown as success');
$probeActivation();
$answer(['protocol_version' => 1, 'status' => 'ok', 'data' => (object) $active], true);
$check($controlledState()['operation']['status'] === 'pending', 'Retained activation acknowledgement cannot complete the operation');
$answer(['protocol_version' => 1, 'status' => 'ok', 'data' => (object) $active]);
$check(str_ends_with($last()['Topic'], '/request/status') && !$controlledState()['runtimeConfirmed'], 'Acknowledgement is followed by an independent status verification');
$replyStatus($inactive);
$check($controlledState()['lastError'] === 'runtime_mismatch', 'Final stopped state cannot confirm an activation');
$probeActivation();
$controlled->clock += 11;
$controlled->ExpireRequest();
$check($controlledState()['operation']['status'] === 'timeout' && !$controlledState()['runtimeConfirmed'], 'Activation timeout leaves the state unknown');
$probeActivation();
$controlled->StopAutomations();
$replyStatus($active);
$check(str_ends_with($last()['Topic'], '/request/deactivate'), 'Stop supersedes pending activation and uses a fresh session');
$answer(['protocol_version' => 1, 'status' => 'ok', 'data' => (object) $inactive]);
$replyStatus($inactive);
$check($controlledState()['operation']['status'] === 'stopped' && $controlledState()['runtimeConfirmed'], 'Only the confirmed inactive state completes stop');
$parent->publish('synthetic/other/bridge/state', ['state' => 'offline']);
$check($controlled->StopAutomations() !== '', 'A stop attempt is permitted on active MQTT even with unknown/offline bridge state');
$replyStatus($active);
$answer(['protocol_version' => 1, 'status' => 'ok', 'data' => (object) $inactive]);
$replyStatus($inactive);
$check($controlledState()['operation']['status'] === 'stopped', 'Stop can establish reachability through the receiver');
$probeActivation();
$controlled->StopAutomations();
$restartedInactive = $inactive;
$restartedInactive['session'] = '44444444-4444-4444-8444-444444444444';
$parent->publish('synthetic/other/symcon/automations/status', (object) $restartedInactive, true);
$check(!array_key_exists('workflow', $controlledState()), 'Export never exposes a pending workflow');
$replyStatus($restartedInactive);
$check(str_ends_with($last()['Topic'], '/request/deactivate'), 'A stop probe may learn a new receiver session after a concurrent restart');
$answer(['protocol_version' => 1, 'status' => 'ok', 'data' => (object) $restartedInactive]);
$replyStatus($restartedInactive);
$check($controlledState()['operation']['status'] === 'stopped', 'Stop is confirmed against the new session');

echo "PHP-to-JavaScript installation, validation and persistence integration passed.\n";
