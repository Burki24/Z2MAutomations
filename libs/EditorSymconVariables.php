<?php

declare(strict_types=1);

namespace Z2MAutomations;

use Throwable;

trait EditorSymconVariables
{
    private ?array $editorActionVariables = null;

    private function EditorActionVariables(): array
    {
        if ($this->editorActionVariables !== null) {
            return $this->editorActionVariables;
        }
        $result = [];
        foreach (array_slice(IPS_GetInstanceListByModuleID('{E5BB36C6-A70B-EB23-3716-9151A09AC8A2}'), 0, 512) as $instance) {
            try {
                $ieee = strtolower(IPS_GetProperty($instance, 'IEEE'));
                if (IPS_GetProperty($instance, 'MQTTBaseTopic') !== $this->ReadPropertyString('BaseTopic') || preg_match('/\A0x[a-f0-9]{16}\z/D', $ieee) !== 1) {
                    continue;
                }
                foreach (IPS_GetChildrenIDs($instance) as $id) {
                    $object = IPS_GetObject($id);
                    if ($object['ObjectType'] !== 2 || $object['ObjectIdent'] === '') {
                        continue;
                    }
                    $property = $object['ObjectIdent'];
                    $binding = ['id' => $id, 'instance' => $instance, 'name' => $object['ObjectName']];
                    if (array_key_exists($property, $result[$ieee] ?? [])) {
                        $result[$ieee][$property] = null;
                    } else {
                        $result[$ieee][$property] = $binding;
                    }
                }
            } catch (Throwable) {
                // A concurrently removed instance is not an actionable source.
            }
        }
        return $this->editorActionVariables = $result;
    }

    private function EditorActionVariable(array $snapshot, string $device, string $property): ?array
    {
        $binding = $this->EditorActionVariables()[$device][$property] ?? null;
        if ($binding === null) {
            return null;
        }
        try {
            $object = IPS_GetObject($binding['id']);
            $variable = IPS_GetVariable($binding['id']);
            if ($object['ParentID'] !== $binding['instance'] || $object['ObjectIdent'] !== $property
                || IPS_GetProperty($binding['instance'], 'IEEE') !== $device
                || IPS_GetProperty($binding['instance'], 'MQTTBaseTopic') !== $this->ReadPropertyString('BaseTopic')
                || ($variable['VariableIsLocked'] ?? false)) {
                return null;
            }
            $custom = $variable['VariableCustomAction'];
            $action = $custom !== 0 ? $custom : $variable['VariableAction'];
            if ($action <= 1 || ($custom > 1 ? !IPS_ScriptExists($action) : !IPS_InstanceExists($action))) {
                return null;
            }
            return $binding;
        } catch (Throwable) {
            return null;
        }
    }

    private function FilterEditorActions(array $snapshot): array
    {
        if (!isset($snapshot['devices'])) {
            return $snapshot;
        }
        foreach ($snapshot['devices'] as $device => &$entry) {
            if (!isset($entry['properties'])) {
                $entry['properties'] = $this->ReadEditorProperties((object) [], $entry) ?? [];
                foreach ($entry['numeric'] ?? [] as $property => $unit) {
                    $entry['properties'][$property] ??= ['property' => $property, 'type' => 'numeric', 'access' => 1, 'label' => $property, 'endpoint' => '', 'values' => []];
                }
            }
            $entry['actions'] = array_filter($entry['actions'], fn (array $choice): bool => $this->EditorActionVariable($snapshot, $device, $choice['property']) !== null);
        }
        unset($entry);
        return $snapshot;
    }
}
