<?php

declare(strict_types=1);

// Two modules and gateways keep identical group/device IDs in separate namespaces.
IPS_SetProperty($otherModuleID, 'BaseTopic', 'synthetic/Z2M-2');
IPS_SetProperty($otherModuleID, 'PeerModuleID', $id);
IPS_ApplyChanges($otherModuleID);
$otherModule->CopySyntheticImportCatalog($module);
$exportedTargets = json_decode($otherModule->GetActionCatalog(), true, 32, JSON_THROW_ON_ERROR);
$check($exportedTargets['devices'] !== [], 'Peer exports its own switchable catalog without requesting or switching devices');
IPS_SetProperty($id, 'PeerModuleID', $otherModuleID);
IPS_ApplyChanges($id);
$parent->publish('synthetic/z2m/bridge/state', ['state' => 'online']);
$module->LoadAutomationEditor();
$remoteFields = formFields(json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR)['actions']);
$groupNumber = IPS_GetProperty(IPS_GetObject($otherTarget)['ParentID'], 'GroupId');
$remoteKey = 'remote.' . hash('sha256', 'synthetic/Z2M-2') . '.group.' . $groupNumber;
$remoteOptions = $remoteFields['DeviceEditorTarget']['options'];
$check(in_array($remoteKey, array_column($remoteOptions, 'value'), true), 'Remote group is offered alongside local targets with the system name');
$remoteCaption = array_values(array_filter($remoteOptions, static fn (array $option): bool => $option['value'] === $remoteKey))[0]['caption'];
$check(str_contains($remoteCaption, 'synthetic/Z2M-2') && !str_contains($remoteCaption, 'remote.'), 'Remote captions show the system and device rather than an opaque transport key');
$check(!in_array($remoteKey, array_column($remoteFields['DeviceEditorSource']['options'], 'value'), true), 'Remote targets are not accidentally offered as local triggers');
$module->importEvents[910014] = $crossTarget;
$check($module->PreviewEventImport(910014), 'Explicitly selected peer permits a local button with a remote group action');
$remoteFields = formFields(json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR)['actions']);
$check($module->ImportSymconEvent(910014, $remoteFields['ImportToken']['value'], $remoteFields['YamlInput']['value'], true), 'Cross-system import creates a disabled draft');
$remoteFields = formFields(json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR)['actions']);
$remoteDocument = json_decode($remoteFields['YamlInput']['value'], true, 32, JSON_THROW_ON_ERROR);
$remoteRule = $remoteDocument['automations'][array_key_last($remoteDocument['automations'])];
$check(!$remoteRule['enabled'] && $remoteRule['actions'][0]['target'] === ['type' => 'group', 'id' => $groupNumber, 'system' => 'synthetic/Z2M-2'], 'YAML identifies the remote system and group without Symcon IDs');
$check($remoteDocument['mesh_peers'] === ['synthetic/Z2M-2'], 'The stored rule document contains the selected peer permission');
$check($module->SelectAutomation($remoteRule['id']), 'Remote rule remains selectable');
$remoteFields = formFields(json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR)['actions']);
$check($remoteFields['DeviceEditorTarget']['value'] === $remoteKey && count($remoteFields['DeviceEditorActions']['values']) === 1, 'Remote target and action are prefilled for editing');
$check($module->ValidateAutomationDraft() !== '' && $state()['operation']['status'] === 'validated', 'Real JavaScript schema validates the cross-system draft');
$check($module->StoreAutomationDraft() !== '' && $state()['operation']['status'] === 'stored', 'Cross-system draft persists without activating either system');
$check(!$state()['extension']['executing'], 'Imported cross-system rules remain stopped');
IPS_SetVariableCustomAction($otherTarget, 1);
$remoteFields = formFields(json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR)['actions']);
$check(!in_array($remoteKey, array_column($remoteFields['DeviceEditorTarget']['options'], 'value'), true), 'Removing the native action immediately removes the remote target');
IPS_SetVariableCustomAction($otherTarget, 0);
IPS_SetProperty($id, 'PeerModuleID', 0);
IPS_ApplyChanges($id);
$check(!$module->PreviewEventImport(910014), 'Removing the peer revokes cross-system import without changing existing drafts or events');
