<?php

declare(strict_types=1);

// A category and an unrelated module reproduce the reported kernel warning boundary.
$category = IPS_CreateCategory();
$standalone = IPS_CreateVariable(3);
IPS_SetParent($standalone, $category);
$foreignVariable = IPS_CreateVariable(3);
IPS_SetParent($foreignVariable, $parentID);
$badEvents = [];
foreach ([$standalone, $foreignVariable] as $n => $variableID) {
    $record = $sourceRecord;
    $record['event']['TriggerVariableID'] = $variableID;
    $badEvents[910000 + $n] = $record;
}
// A second gateway contains identical IEEE/group identities and two different topics.
IPS\ModuleLoader::loadSingleModule(__DIR__ . '/fixtures/MQTTServer', $library['id']);
$otherGateway = IPS_CreateInstance('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
IPS_ApplyChanges($otherGateway);
$otherModuleID = IPS_CreateInstance($metadata['id']);
IPS_ConnectInstance($otherModuleID, $otherGateway);
IPS_SetProperty($otherModuleID, 'Active', true);
IPS_SetProperty($otherModuleID, 'BaseTopic', 'synthetic/Z2M-2');
IPS_ApplyChanges($otherModuleID);
$otherModule = IPS\InstanceManager::getInstanceInterface($otherModuleID);
$makeSource = static function (int $gateway, string $topic) use ($check): int
{
    $device = IPS_CreateInstance('{E5BB36C6-A70B-EB23-3716-9151A09AC8A2}');
    IPS_ConnectInstance($device, $gateway);
    IPS_SetProperty($device, 'IEEE', '0x0000000000000001');
    IPS_SetProperty($device, 'MQTTBaseTopic', $topic);
    IPS_ApplyChanges($device);
    return IPS_GetObjectIDByIdent('action', $device);
};
$makeGroup = static function (int $gateway, string $topic): int
{
    $instance = IPS_CreateInstance('{11BF3773-E940-469B-9DD7-FB9ACD7199A2}');
    IPS_ConnectInstance($instance, $gateway);
    IPS_SetProperty($instance, 'MQTTBaseTopic', $topic);
    IPS_ApplyChanges($instance);
    return IPS_GetObjectIDByIdent('state', $instance);
};
$otherSource = $makeSource($otherGateway, 'synthetic/Z2M-2');
$otherTarget = $makeGroup($otherGateway, 'synthetic/Z2M-2');
$sameTopicForeignSource = $makeSource($otherGateway, 'synthetic/z2m');
$sameGatewayOtherTopicSource = $makeSource($parentID, 'synthetic/other-topic');
$makeRecord = static function (int $source, int $target) use ($sourceRecord): array
{
    $record = $sourceRecord;
    $record['event']['TriggerVariableID'] = $source;
    $record['event']['EventActionParameters'] = ['TARGET' => $target];
    return $record;
};
$events = $badEvents + [
    910010 => $makeRecord($sourceVariable, $targetVariable),
    910011 => $makeRecord($otherSource, $otherTarget),
    910012 => $makeRecord($sameTopicForeignSource, $otherTarget),
    910013 => $makeRecord($sameGatewayOtherTopicSource, $targetVariable)];
$module->importEvents = $otherModule->importEvents = $events;
$module->ScanEventImports();
$otherModule->ScanEventImports();
$withoutCatalog = formFields(json_decode($otherModule->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR)['actions']);
$check(array_column($withoutCatalog['ImportEventID']['options'], 'value') === [0, 910011]
    && !$otherModule->PreviewEventImport(910011), 'Related Symcon events are discovered without a catalog; import still requires Z2M metadata');
$otherModule->CopySyntheticImportCatalog($module);
$otherModule->ScanEventImports();
$firstForm = json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR);
$secondForm = json_decode($otherModule->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR);
$firstFields = formFields($firstForm['actions']);
$secondFields = formFields($secondForm['actions']);
$check(array_column($firstFields['ImportEventID']['options'], 'value') === [0, 910010], 'First module discovers only events on its own gateway and exact topic');
$check(array_column($secondFields['ImportEventID']['options'], 'value') === [0, 910011], 'Second module discovers its independent topic and identical Z2M identities without ambiguity');
$check($module->PreviewEventImport(910010) && $otherModule->PreviewEventImport(910011), 'Each module previews its own discovered event');
$check(!$module->PreviewEventImport(910011) && !$otherModule->PreviewEventImport(910010)
    && !$module->PreviewEventImport(910012), 'Cross-gateway events are rejected even when their base topic or IEEE identity matches');
$crossTarget = $makeRecord($sourceVariable, $otherTarget);
$module->importEvents[910014] = $crossTarget;
$check(!$module->PreviewEventImport(910014), 'A target on another gateway is never imported from a local source');
$check($GLOBALS['invalidImportKernelReads'] === 0, 'Unrelated category variables and modules never cause invalid instance or MQTTBaseTopic reads');
$firstMqtt = formFields($firstForm['elements']);
$secondMqtt = formFields($secondForm['elements']);
$check(array_column($firstMqtt['DetectedBaseTopic']['options'], 'caption') === ['Choose a detected base topic', 'synthetic/other-topic', 'synthetic/z2m'], 'Detected topic options belong exclusively to the first gateway');
$check(array_column($secondMqtt['DetectedBaseTopic']['options'], 'caption') === ['Choose a detected base topic', 'synthetic/Z2M-2', 'synthetic/z2m'], 'The second gateway has its own topic choices');
foreach (array_slice($secondMqtt['DetectedBaseTopic']['options'], 1) as $option) {
    $check($option['value'] === [['name' => 'BaseTopic', 'value' => $option['caption']]], 'Native topic selection changes only the existing BaseTopic field');
}
$check(IPS_GetInstance($id)['ConnectionID'] === $parentID && IPS_GetInstance($otherModuleID)['ConnectionID'] === $otherGateway, 'Discovery and topic selection never alter either gateway assignment');

$otherModule->PreviewEventImport(910011);
IPS_SetProperty($otherModuleID, 'BaseTopic', 'synthetic/z2m');
IPS_ApplyChanges($otherModuleID);
$changed = formFields(json_decode($otherModule->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR)['actions']);
$check(array_column($changed['ImportEventID']['options'], 'value') === [0] && !$changed['ImportButton']['enabled'], 'A topic change hides cached reports and import approval from the old scope');
$check(!$otherModule->PreviewEventImport(910012), 'An old Z2M catalog cannot approve imports after a topic change');
