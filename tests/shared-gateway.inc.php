<?php

declare(strict_types=1);

// A single native MQTT Client serves both Z2M topics; IEEE/group IDs overlap.
foreach ([$otherModuleID, IPS_GetObject($otherSource)['ParentID'], IPS_GetObject($otherTarget)['ParentID']] as $sharedInstance) {
    IPS_DisconnectInstance($sharedInstance);
    IPS_ConnectInstance($sharedInstance, $parentID);
    IPS_ApplyChanges($sharedInstance);
}
$otherModule->CopySyntheticImportCatalog($module);
IPS_SetProperty($id, 'PeerModuleID', $otherModuleID);
IPS_ApplyChanges($id);
$parent->publish('synthetic/z2m/bridge/state', ['state' => 'online']);
$module->LoadAutomationEditor();
$sharedFields = formFields(json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR)['actions']);
$sharedOptions = array_column($sharedFields['DeviceEditorTarget']['options'], 'value');
$check(in_array($remoteKey, $sharedOptions, true), 'A peer on the same MQTT Client is accepted when its base topic differs');
$check(in_array('group.' . $groupNumber, $sharedOptions, true), 'Local and remote groups with the same ID remain separately selectable');
$sharedDebug = array_map(static fn (array $entry): array => json_decode($entry[1], true, 512, JSON_THROW_ON_ERROR), $module->debugMessages);
$check(count(array_filter($sharedDebug, static fn (array $entry): bool => ($entry['Bezug']['GemeinsamesMQTTGateway'] ?? false) === true)) > 0, 'German debug explicitly reports the shared MQTT parent without catalog contents');
$module->importEvents[920010] = $makeRecord($sourceVariable, $targetVariable);
$module->importEvents[920014] = $makeRecord($sourceVariable, $otherTarget);
$module->importEvents[920015] = $makeRecord($otherSource, $targetVariable);
$check($module->PreviewEventImport(920010), 'Local action remains local while a peer shares its MQTT Client');
$check(!$module->PreviewEventImport(920015), 'The peer topic never becomes a local trigger despite the common MQTT Client and IEEE');
$check(!$module->PreviewEventImport(910012), 'Matching IEEE and topic on an unselected connection remain excluded');
$check($module->PreviewEventImport(920014), 'Remote group imports are classified by base topic on a shared MQTT Client');
$sharedFields = formFields(json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR)['actions']);
$check($module->ImportSymconEvent(920014, $sharedFields['ImportToken']['value'], $sharedFields['YamlInput']['value'], true), 'Shared-client cross-system event imports as a disabled draft');
$sharedFields = formFields(json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR)['actions']);
$sharedDocument = json_decode($sharedFields['YamlInput']['value'], true, 32, JSON_THROW_ON_ERROR);
$sharedRule = $sharedDocument['automations'][array_key_last($sharedDocument['automations'])];
$check(!$sharedRule['enabled'] && $sharedRule['actions'][0]['target']['system'] === 'synthetic/Z2M-2', 'The shared connection cannot erase the remote system identity');
$check($module->SelectAutomation($sharedRule['id']), 'Shared-client rule can be selected for editing');
$sharedFields = formFields(json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR)['actions']);
$check($sharedFields['DeviceEditorTarget']['value'] === $remoteKey, 'Shared-client target is correctly prefilled');
$check($module->ValidateAutomationDraft() !== '' && $state()['operation']['status'] === 'validated', 'Shared-client draft passes the real Z2M schema');
$check($module->StoreAutomationDraft() !== '' && $state()['operation']['status'] === 'stored' && !$state()['extension']['executing'], 'Shared-client rules persist without activation');
IPS_SetVariableCustomAction($otherTarget, 1);
$sharedFields = formFields(json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR)['actions']);
$sharedOptions = array_column($sharedFields['DeviceEditorTarget']['options'], 'value');
$check(!in_array($remoteKey, $sharedOptions, true) && in_array('group.' . $groupNumber, $sharedOptions, true), 'Revoking remote rights does not hide the equal-ID local group');
IPS_SetVariableCustomAction($otherTarget, 0);
IPS_SetProperty($otherModuleID, 'BaseTopic', 'synthetic/z2m');
IPS_ApplyChanges($otherModuleID);
$module->GetConfigurationForm();
$check($state()['mqttConnected'] === false, 'Two partner modules with the same base topic remain invalid');
IPS_SetProperty($otherModuleID, 'BaseTopic', 'synthetic/Z2M-2');
IPS_ApplyChanges($otherModuleID);
IPS_SetProperty($id, 'PeerModuleID', $id);
IPS_ApplyChanges($id);
$check($state()['mqttConnected'] === false, 'A module cannot select itself as its peer');
IPS_SetProperty($id, 'PeerModuleID', 0);
IPS_ApplyChanges($id);
