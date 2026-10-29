<?php

declare(strict_types=1);

namespace Z2MAutomations;

use Throwable;

trait EditorGroupTargets
{
    private array $editorGroupBindings = [];
    private function ReadEditorGroups(object $data): ?array
    {
        if (!is_array($data->groups ?? []) || count($data->groups ?? []) > 256) {
            return null;
        }
        $groups = [];
        foreach ($data->groups ?? [] as $group) {
            if (!is_object($group) || !is_int($group->id ?? null) || $group->id < 1 || $group->id > 65535
                || !is_string($group->name ?? null) || $group->name === '' || strlen($group->name) > 480
                || preg_match('/[\x00-\x1f\x7f+#]/', $group->name) || isset($groups[$group->id])) {
                return null;
            }
            $groups[$group->id] = $group->name;
        }
        return $groups;
    }

    private function AddEditorGroupTargets(array $snapshot): array
    {
        foreach ($snapshot['groups'] ?? [] as $id => $name) {
            $choices = [];
            foreach (['ON', 'OFF', 'TOGGLE'] as $value) {
                $choice = ['property' => 'state', 'value' => $value];
                $choices[hash('sha256', json_encode($choice, JSON_THROW_ON_ERROR))] = $choice;
            }
            $snapshot['devices']['group.' . $id] = ['name' => $name . ' (' . $this->Translate('Group') . ')',
                'triggers'                                 => [], 'numeric' => [], 'actions' => $choices,
                'properties'                               => ['state' => ['property' => 'state', 'type' => 'binary', 'access' => 2,
                    'label'                                                           => 'State', 'endpoint' => '', 'values' => ['ON', 'OFF']]]];
        }
        return $snapshot;
    }

    private function EditorGroupVariable(array $snapshot, string $key, string $property): ?array
    {
        $id = (int) substr($key, 6);
        if ($key !== 'group.' . $id || !isset($snapshot['groups'][$id]) || $property !== 'state') {
            return null;
        }
        if (array_key_exists($key, $this->editorGroupBindings)) {
            return $this->editorGroupBindings[$key];
        }
        $found = null;
        foreach ($this->GatewayZ2MInstances() as $instance => $scope) {
            try {
                if ($scope['type'] !== 'group' || $scope['key'] !== $key || $scope['topic'] !== $this->ReadPropertyString('BaseTopic') || !IPS_InstanceExists($instance)) {
                    continue;
                }
                foreach (IPS_GetChildrenIDs($instance) as $variableID) {
                    if (!IPS_ObjectExists($variableID)) {
                        continue;
                    }
                    $object = IPS_GetObject($variableID);
                    if ($object['ObjectType'] !== 2 || $object['ObjectIdent'] !== 'state') {
                        continue;
                    }
                    $variable = IPS_GetVariable($variableID);
                    $action = $variable['VariableCustomAction'] !== 0 ? $variable['VariableCustomAction'] : $variable['VariableAction'];
                    if ($variable['VariableType'] !== 0 || ($variable['VariableIsLocked'] ?? false) || $action <= 1
                        || ($variable['VariableCustomAction'] > 1 ? !IPS_ScriptExists($action) : !IPS_InstanceExists($action))) {
                        continue;
                    }
                    if ($found !== null) {
                        return $this->editorGroupBindings[$key] = null;
                    }
                    $found = ['id' => $variableID, 'instance' => $instance, 'name' => $object['ObjectName']];
                }
            } catch (Throwable) {
                return null;
            }
        }
        return $this->editorGroupBindings[$key] = $found;
    }

    private function EditorTargetIdentity(string $key): array
    {
        if (str_starts_with($key, 'remote.')) {
            return $this->EditorSnapshot()['devices'][$key]['remoteTarget'] ?? [];
        }
        return str_starts_with($key, 'group.') ? ['type' => 'group', 'id' => (int) substr($key, 6)] : ['type' => 'device', 'id' => $key];
    }

    private function EditorTargetKey(array $target): string
    {
        $local = ($target['type'] ?? '') === 'group' ? 'group.' . $target['id'] : (string) ($target['id'] ?? '');
        return isset($target['system']) ? 'remote.' . hash('sha256', $target['system']) . '.' . $local : $local;
    }
}
