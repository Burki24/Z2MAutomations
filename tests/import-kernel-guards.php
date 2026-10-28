<?php

declare(strict_types=1);

namespace Z2MAutomations;

// The official stubs throw on invalid reads; the real kernel emits warnings.
// Count forbidden reads even when production catches the stub's exception.
function IPS_GetInstance(int $id): mixed
{
    if (!\IPS_InstanceExists($id)) {
        $GLOBALS['invalidImportKernelReads']++;
    }
    return \IPS_GetInstance($id);
}

function IPS_GetProperty(int $id, string $name): mixed
{
    if ($name === 'MQTTBaseTopic') {
        $known = ['{E5BB36C6-A70B-EB23-3716-9151A09AC8A2}', '{11BF3773-E940-469B-9DD7-FB9ACD7199A2}', '{00160D82-9E2F-D1BD-6D0B-952F945332C5}'];
        if (!\IPS_InstanceExists($id) || !in_array(\IPS_GetInstance($id)['ModuleInfo']['ModuleID'], $known, true)) {
            $GLOBALS['invalidImportKernelReads']++;
        }
    }
    return \IPS_GetProperty($id, $name);
}
