<?php

declare(strict_types=1);

require __DIR__ . '/stubs/autoload.php';

IPS\Kernel::reset();
IPS\ModuleLoader::loadLibrary(dirname(__DIR__) . '/library.json');
$metadata = json_decode(file_get_contents(dirname(__DIR__) . '/Z2MAutomations/module.json'), true, 512, JSON_THROW_ON_ERROR);
$id = IPS_CreateInstance($metadata['id']);
IPS_ApplyChanges($id);
IPS_ApplyChanges($id);

if (IPS_GetInstance($id)['InstanceStatus'] !== 104) {
    throw new RuntimeException('The scaffold must remain inactive.');
}
if (IPS_GetInstance($id)['ConnectionID'] !== 0 || IPS_GetChildrenIDs($id) !== []) {
    throw new RuntimeException('The scaffold must not create connections or child objects.');
}

echo "Module creation and repeated ApplyChanges passed.\n";
