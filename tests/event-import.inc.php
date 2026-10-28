<?php

declare(strict_types=1);

// Synthetic kernel event boundary; real module/form/editor and real JS peer below.
IPS\ModuleLoader::loadSingleModule(__DIR__ . '/fixtures/Z2MGroup', $library['id']);
$groupID = IPS_CreateInstance('{11BF3773-E940-469B-9DD7-FB9ACD7199A2}');
IPS_ConnectInstance($groupID, $parentID);
IPS_ApplyChanges($groupID);
$sourceVariable = IPS_GetObjectIDByIdent('action', $syntheticDevices[1]);
$targetVariable = IPS_GetObjectIDByIdent('state', $groupID);
$sourceRecord = ['name' => 'Synthetic private import event', 'event' => [
    'EventType'                  => 0, 'TriggerType' => 4, 'TriggerVariableID' => $sourceVariable, 'TriggerValue' => 'single',
    'TriggerSubsequentExecution' => true, 'EventLimit' => 0, 'EventConditions' => [], 'EventScript' => '', 'EventActive' => true,
    'EventActionID'              => '{EF078D00-FBD5-8857-F5DC-F5B54B107DD3}', 'EventActionParameters' => ['TARGET' => $targetVariable]]];
$module->importEvents = [900001 => $sourceRecord];
$module->ClearSyntheticImportCatalog();
$importFields = static fn (): array => formFields(json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR)['actions']);
$fields = $importFields();
$module->ScanEventImports();
$fields = $importFields();
$check($fields['ImportEvents']['values'][0]['Device'] === IPS_GetName($syntheticDevices[1])
    && $fields['ImportEvents']['values'][0]['Instance'] === $syntheticDevices[1], 'Search overview identifies the source device by actual name and instance ID');
$check(str_contains($fields['ImportEventID']['options'][1]['caption'], IPS_GetName($syntheticDevices[1]))
    && str_contains($fields['ImportEventID']['options'][1]['caption'], (string) $syntheticDevices[1]), 'Event selection distinguishes identical names by source device and instance ID');
$check(!$module->PreviewEventImport(900001) && !$importFields()['ImportButton']['enabled']
    && str_contains($importFields()['ImportMessage']['caption'], 'Click "Load device data for import"'), 'Missing device data explains the exact next button instead of an unexplained catalog requirement');
$beforeDataLoad = count($parent->sent);
$beforeDataDraft = $importFields()['YamlInput']['value'];
$check($module->LoadImportDevices(900001) === '' && count($parent->sent) === $beforeDataLoad
    && $importFields()['YamlInput']['value'] === $beforeDataDraft, 'Declined device loading leaves the local draft and MQTT untouched');
$normalPeer = $parent->onPublish;
$deferredData = [];
$parent->onPublish = static function (array $packet) use ($exchange, &$deferredData): void
{
    $deferredData = $exchange(['topic' => $packet['Topic'], 'payload' => $packet['Payload']]);
};
$ImportEventID = 900001;
$fields = $importFields();
eval($fields['ImportLoadButton']['onClick']);
$waiting = $importFields();
$check(!$waiting['ImportButton']['enabled'] && !$waiting['ImportLoadButton']['enabled'] && !$waiting['ImportEventID']['enabled'] && $waiting['ImportEventID']['value'] === 900001
    && str_contains($waiting['ImportMessage']['caption'], 'Loading device data'), 'Delayed MQTT responses keep the selection visible without prematurely enabling import');
$parent->onPublish = $normalPeer;
$deliver($deferredData);
$check(!str_contains($module->formUpdates['ImportMessage']['caption'], 'Loading device data')
    && $module->formUpdates['ImportButton']['enabled'] && $module->formUpdates['ImportLoadButton']['enabled'], 'Successful loading updates the open form without requiring it to be reopened');
$fields = $importFields();
$check($fields['ImportButton']['enabled'] && $fields['ImportEventID']['value'] === 900001 && $fields['ImportLoadButton']['enabled']
    && str_contains($fields['ImportEvents']['values'][0]['Result'], 'Supported.'), 'A confirmed editor response automatically refreshes the report and selected preview');
// A timeout must remain understandable after reopening the form and permit retry.
$parent->onPublish = static function (array $packet): void
{};
$module->LoadImportDevices(900001, true);
$pendingData = json_decode($module->ReplaceTransportBuffer(''), true, 32, JSON_THROW_ON_ERROR);
$pendingData['workflow']['deadline'] = 1;
$module->ReplaceTransportBuffer(json_encode($pendingData, JSON_THROW_ON_ERROR));
$module->ExpireRequest();
$failedLoad = $importFields();
$check(!$failedLoad['ImportButton']['enabled'] && $failedLoad['ImportLoadButton']['enabled']
    && str_contains($failedLoad['ImportMessage']['caption'], 'Device data could not be loaded:'), 'Loading errors survive form reopening and never grant import approval');
$parent->onPublish = $normalPeer;
$module->LoadImportDevices(900001, true);
$check($importFields()['ImportButton']['enabled'], 'Retrying device load confirms metadata and re-enables the selected supported event');
$fields = $importFields();
$beforeImport = count($parent->sent);
eval($fields['EventImportSection']['items'][1]['onClick']);
$fields = $importFields();
$check(count($fields['ImportEvents']['values']) === 1 && count($parent->sent) === $beforeImport, 'Search reads related kernel events without MQTT or event mutations');
$ImportEventID = 900001;
eval($fields['ImportEventID']['onChange']);
$fields = $importFields();
$check($fields['ImportButton']['enabled'] && $fields['ImportToken']['value'] !== '', 'Selecting a supported event prepares a usable preview');
$check(str_contains($fields['ImportMessage']['caption'], 'Single press') && str_contains($fields['ImportMessage']['caption'], 'Toggle'), 'Preview explains source value, group target and toggle action');
$YamlInput = $fields['YamlInput']['value'];
$ImportToken = $fields['ImportToken']['value'];
eval($fields['ImportButton']['onClick']);
$fields = $importFields();
$importedConfiguration = json_decode($fields['YamlInput']['value'], true, 512, JSON_THROW_ON_ERROR);
$importedRule = $importedConfiguration['automations'][array_key_last($importedConfiguration['automations'])];
$check($importedRule['enabled'] === false && $importedRule['triggers'][0]['device'] === '0x0000000000000001'
    && $importedRule['triggers'][0]['value'] === 'single' && $importedRule['actions'][0]['target'] === ['type' => 'group', 'id' => 7]
    && $importedRule['actions'][0]['type'] === 'toggle' && $importedRule['actions'][0]['property'] === 'state', 'Import creates a disabled group toggle rule from exact variable identities');
$check($module->importEvents[900001] === $sourceRecord && count($parent->sent) === $beforeImport, 'Import leaves the active source event and Z2M unchanged');
$check($fields['DeviceEditorName']['value'] === $sourceRecord['name'] && $fields['DeviceEditorEnabled']['value'] === false
    && count($fields['DeviceEditorTriggers']['values']) === 1 && count($fields['DeviceEditorActions']['values']) === 1
    && $fields['DeviceEditorActions']['enabled'], 'Imported rule opens in the native device editor with all rows prefilled and editable');
$groupChoice = $fields['DeviceEditorActions']['values'][0]['Choice'];
$rowForm = formFields(json_decode($module->GetEditorItemForm('DeviceEditorActions', $groupChoice, 0), true, 512, JSON_THROW_ON_ERROR));
$check($rowForm['RowDevice']['value'] === 'group.7' && $rowForm['RowProperty']['value'] === (string) $targetVariable
    && in_array($rowForm['RowValue']['value'], array_column($rowForm['RowValue']['options'], 'value'), true), 'Group, real variable and toggle value reopen correctly in the item dialog');
$runEditorScript($fields['DeviceUpdateRuleButton']['onClick'], $fields, [
    'DeviceEditorName'          => $sourceRecord['name'], 'DeviceEditorEnabled' => false,
    'DeviceEditorActions'       => new ArrayIterator($fields['DeviceEditorActions']['values']),
    'DeviceEditorTriggers'      => new ArrayIterator($fields['DeviceEditorTriggers']['values']),
    'DeviceEditorConditionList' => new ArrayIterator([])]);
$check($state()['operation']['status'] === 'draft_updated', 'Actual native editor callback preserves the imported group toggle action');
$fields = $importFields();
$module->ValidateYaml($fields['YamlInput']['value']);
$check($state()['operation']['status'] === 'validated', 'Real JS receiver validates the imported group rule');
$module->StoreYaml($fields['YamlInput']['value']);
$check($state()['operation']['status'] === 'stored' && !$state()['extension']['executing'], 'Imported rules persist without activating');
$module->LoadAutomationEditor();
$fields = $importFields();
$check($module->SelectAutomation($importedRule['id'], $fields['YamlInput']['value'], true, 'device'), 'Stored import remains selectable after reloading the real receiver');
$module->PreviewEventImport(900001);
$fields = $importFields();
$check(!$module->ImportSymconEvent(900001, $fields['ImportToken']['value'], $fields['YamlInput']['value'], true), 'Repeated import never duplicates or replaces an existing imported rule');
// A native fixed-value action on another channel, same display name, separate identity.
$second = $sourceRecord;
$second['event']['EventActionID'] = '{46B65CA6-3098-4982-9A6C-B89DADBB0A96}';
$second['event']['EventActionParameters'] = ['TARGET' => IPS_GetObjectIDByIdent('state_l2', $syntheticDevices[4]), 'VALUE' => true];
$module->importEvents[900002] = $second;
$check($module->PreviewEventImport(900002), 'Native boolean channel action is supported');
$fields = $importFields();
$staleToken = $fields['ImportToken']['value'];
$module->importEvents[900002]['event']['TriggerValue'] = 'double';
$check(!$module->ImportSymconEvent(900002, $staleToken, $fields['YamlInput']['value'], true), 'Changed event invalidates the preview instead of silently importing different semantics');
$check($module->PreviewEventImport(900002), 'Changed supported event can be previewed again');
$fields = $importFields();
$check(!$module->ImportSymconEvent(900002, $fields['ImportToken']['value'], $fields['YamlInput']['value'] . '# edited', true), 'Unsaved expert edits are never overwritten by import');
$check($module->ImportSymconEvent(900002, $fields['ImportToken']['value'], $fields['YamlInput']['value'], true), 'Fresh preview imports exact native channel action');
$fields = $importFields();
$secondRule = json_decode($fields['YamlInput']['value'], true, 512, JSON_THROW_ON_ERROR)['automations'];
$secondRule = $secondRule[array_key_last($secondRule)];
$check($secondRule['name'] === $importedRule['name'] && $secondRule['id'] !== $importedRule['id']
    && $secondRule['actions'][0]['values'] === ['state_l2' => 'ON'], 'Same-name events remain distinct and boolean values map to the exact channel command');
foreach ([['TriggerSubsequentExecution' => false], ['TriggerType'   => 1], ['EventLimit' => 1], ['EventConditions' => [['Operation' => 0]]],
    ['EventScript' => 'synthetic-private-script'], ['EventActionID' => '{346AA8C1-30E0-1663-78EF-93EFADFAC650}'],
    ['EventActionParameters' => ['TARGET' => $targetVariable, 'EXTRA' => 'synthetic-private-parameter']]] as $unsupported) {
    $module->importEvents[900003] = ['name' => $sourceRecord['name'], 'event' => array_replace($sourceRecord['event'], $unsupported)];
    $check(!$module->PreviewEventImport(900003) && !$importFields()['ImportButton']['enabled'], 'Unsupported behavior is rejected completely without partial conversion');
}
$numericImport = $second;
$numericImport['event']['EventActionID'] = '{A4C53C8D-795E-403A-9EEC-CC0D71E89A20}';
$numericImport['event']['EventActionParameters'] = ['TARGET' => IPS_GetObjectIDByIdent('level', $syntheticDevices[4]), 'VALUE' => 50];
$module->importEvents[900003] = $numericImport;
$check(!$module->PreviewEventImport(900003), 'Numeric values with unproven Symcon scaling are never silently copied');
$module->importEvents[900003] = $sourceRecord;
IPS_SetVariableCustomAction($targetVariable, 1);
$check(!$module->PreviewEventImport(900003), 'Disabled target actions cannot be imported');
IPS_SetVariableCustomAction($targetVariable, 0);
$module->PreviewEventImport(900003);
$fields = $importFields();
IPS_SetProperty($groupID, 'MQTTBaseTopic', 'synthetic/other');
IPS_ApplyChanges($groupID);
$check(!$module->ImportSymconEvent(900003, $fields['ImportToken']['value'], $fields['YamlInput']['value'], true), 'Changed broker scope invalidates target mapping at import time');
IPS_SetProperty($groupID, 'MQTTBaseTopic', 'synthetic/z2m');
IPS_ApplyChanges($groupID);
IPS_SetName($sourceVariable, 'Synthetic renamed action');
$check($module->PreviewEventImport(900003), 'Variable renaming preserves identity-based mapping');
$duplicateGroup = IPS_CreateInstance('{11BF3773-E940-469B-9DD7-FB9ACD7199A2}');
IPS_ConnectInstance($duplicateGroup, $parentID);
IPS_ApplyChanges($duplicateGroup);
$check(!$module->PreviewEventImport(900003), 'Ambiguous group identities are rejected instead of choosing a variable by name');
IPS_SetProperty($duplicateGroup, 'GroupId', 8);
IPS_ApplyChanges($duplicateGroup);
unset($module->importEvents[900003]);
$check(!$module->PreviewEventImport(900003), 'Deleted events are handled without exceptions');
// Execute the generated rule only inside the synthetic peer, never on a live host.
$module->LoadAutomationEditor();
$module->SelectAutomation($importedRule['id']);
$fields = $importFields();
$runEditorScript($fields['DeviceUpdateRuleButton']['onClick'], $fields, [
    'DeviceEditorEnabled'       => true,
    'DeviceEditorActions'       => new ArrayIterator($fields['DeviceEditorActions']['values']),
    'DeviceEditorTriggers'      => new ArrayIterator($fields['DeviceEditorTriggers']['values']),
    'DeviceEditorConditionList' => new ArrayIterator([])]);
$check($module->ValidateAutomationDraft() !== '' && $module->StoreAutomationDraft() !== '', 'Native editor can enable and persist the imported rule');
$check($module->ActivateAutomations() !== '' && $state()['extension']['executing'], 'Synthetic receiver activates the imported rule');
$commands = $exchange(['control' => 'event', 'device' => '0x0000000000000001', 'payload' => ['action' => 'single']]);
$groupCommands = array_values(array_filter($commands, static fn (object $message): bool => $message->topic === 'synthetic/z2m/synthetic/group/set'));
$check(count($groupCommands) === 1 && $groupCommands[0]->payload->state === 'OFF', 'Imported event executes exactly one uniform group OFF command from the peer ON cache');
$check($module->StopAutomations() !== '' && !$state()['extension']['executing'], 'Synthetic import execution is stopped after verification');
$importDebug = json_encode($module->debugMessages, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
$check(!str_contains($importDebug, 'Synthetic private import event') && !str_contains($importDebug, 'synthetic-private-script')
    && !str_contains($importDebug, 'synthetic-private-parameter') && !str_contains($importDebug, $staleToken)
    && str_contains($importDebug, 'deaktivierte Regel'), 'German import diagnostics explain decisions without names, values, scripts or tokens');
