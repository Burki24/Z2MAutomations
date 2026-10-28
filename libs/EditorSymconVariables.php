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
        foreach ($this->GatewayZ2MInstances() as $instance => $scope) {
            try {
                $ieee = $scope['key'];
                if ($scope['type'] !== 'device' || $scope['topic'] !== $this->ReadPropertyString('BaseTopic') || !IPS_InstanceExists($instance)) {
                    continue;
                }
                foreach (IPS_GetChildrenIDs($instance) as $id) {
                    if (!IPS_ObjectExists($id)) {
                        continue;
                    }
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
        if (str_starts_with($device, 'group.')) {
            return $this->EditorGroupVariable($snapshot, $device, $property);
        }
        $binding = $this->EditorActionVariables()[$device][$property] ?? null;
        if ($binding === null) {
            return null;
        }
        try {
            if (!IPS_ObjectExists($binding['id']) || !IPS_VariableExists($binding['id'])) {
                return null;
            }
            $scope = $this->ReadZ2MInstanceScope($binding['instance']);
            $object = IPS_GetObject($binding['id']);
            $variable = IPS_GetVariable($binding['id']);
            if ($object['ParentID'] !== $binding['instance'] || $object['ObjectIdent'] !== $property
                || ($scope['type'] ?? '') !== 'device' || ($scope['key'] ?? '') !== $device
                || ($scope['topic'] ?? '') !== $this->ReadPropertyString('BaseTopic')
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
