<?php

declare(strict_types=1);

require __DIR__ . '/stubs/autoload.php';

IPS\Kernel::reset();
$library = json_decode(file_get_contents(dirname(__DIR__) . '/library.json'), true, 512, JSON_THROW_ON_ERROR);
IPS\ModuleLoader::loadSingleModule(dirname(__DIR__) . '/Z2MAutomations', $library['id']);
$metadata = json_decode(file_get_contents(dirname(__DIR__) . '/Z2MAutomations/module.json'), true, 512, JSON_THROW_ON_ERROR);
$id = IPS_CreateInstance($metadata['id']);
IPS_ApplyChanges($id);
IPS_ApplyChanges($id);

if (IPS_GetInstance($id)['InstanceStatus'] !== 104) {
    throw new RuntimeException('The scaffold must remain inactive.');
}
if (IPS_GetInstance($id)['ConnectionID'] !== 0 || count(IPS_GetChildrenIDs($id)) !== 14) {
    throw new RuntimeException('Create only the fourteen status variables and no connection.');
}

$children = IPS_GetChildrenIDs($id);
IPS_ApplyChanges($id);
if (IPS_GetChildrenIDs($id) !== $children) {
    throw new RuntimeException('ApplyChanges must not duplicate status variables.');
}
foreach ($children as $child) {
    if (IPS_GetVariable($child)['VariableAction'] !== 0) {
        throw new RuntimeException('Status variables must be read-only.');
    }
}

$form = json_decode(file_get_contents(dirname(__DIR__) . '/Z2MAutomations/form.json'), true, 512, JSON_THROW_ON_ERROR);
foreach ($form['elements'] as $field) {
    if (isset($field['name'])) {
        IPS_GetProperty($id, $field['name']);
    }
}

$module = IPS\InstanceManager::getInstanceInterface($id);
$draft = "schema_version: 1\nrevision: 1\nautomations: []\n";
if ($module->ValidateYaml($draft) !== '') {
    throw new RuntimeException('Disabled module cannot send validation requests.');
}
$form = json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR);
$actions = array_column($form['actions'], null, 'name');
if ($actions['YamlInput']['value'] !== $draft) {
    throw new RuntimeException('A valid local draft must survive an offline validation attempt.');
}

echo "Module creation, disabled default and repeated ApplyChanges passed.\n";
