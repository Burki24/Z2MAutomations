<?php

declare(strict_types=1);

namespace Z2MAutomations;

use Throwable;

trait RemoteAutomationTargets
{
    private ?array $peerActionCatalog = null;

    public function GetActionCatalog(): string
    {
        return $this->TraceModuleCall(__FUNCTION__, __LINE__, function (): string
        {
            $snapshot = $this->LocalEditorSnapshot();
            $scope = json_decode($snapshot['scope'] ?? '', true);
            $devices = [];
            if (is_array($scope) && array_slice($scope, 0, 2) === [$this->GetParentID(), $this->ReadPropertyString('BaseTopic')]) {
                foreach ($snapshot['devices'] ?? [] as $key => $entry) {
                    $bindings = [];
                    foreach ($this->EditorProperties($snapshot, $key, 'Target') as $property => $meta) {
                        $binding = $this->EditorActionVariable($snapshot, $key, $property);
                        if ($binding !== null) {
                            $bindings[$property] = $binding;
                        }
                    }
                    if ($bindings !== []) {
                        $devices[$key] = $entry + ['bindings' => $bindings];
                    }
                }
            }
            $this->DebugEvent('AKTION', 'Schaltbare Ziele für das zweite Automationsmodul bereitgestellt; keine Geräte geschaltet.', ['Ziele' => count($devices)]);
            return json_encode(['system' => $this->ReadPropertyString('BaseTopic'), 'gateway' => $this->GetParentID(), 'devices' => $devices], JSON_THROW_ON_ERROR);
        });
    }

    protected function ReadPeerActionCatalog(int $id): array
    {
        return json_decode(Z2MA_GetActionCatalog($id), true, 32, JSON_THROW_ON_ERROR);
    }

    private function PeerContext(): ?array
    {
        $id = $this->ReadPropertyInteger('PeerModuleID');
        if ($id <= 0 || $id === $this->InstanceID || !IPS_InstanceExists($id)) {
            return null;
        }
        try {
            $instance = IPS_GetInstance($id);
            if (($instance['ModuleInfo']['ModuleID'] ?? '') !== '{23D57681-8424-48DC-9FF0-25C110C533A9}') {
                return null;
            }
            $configuration = json_decode(IPS_GetConfiguration($id), true, 32, JSON_THROW_ON_ERROR);
            $base = $configuration['BaseTopic'] ?? '';
            $gateway = $instance['ConnectionID'];
            return $gateway > 0 && $gateway !== $this->GetParentID() && is_string($base) && strlen($base) <= 128
                && preg_match('/\A[A-Za-z0-9_-]+(?:\/[A-Za-z0-9_-]+)*\z/D', $base) === 1 && $base !== $this->ReadPropertyString('BaseTopic')
                ? ['module' => $id, 'system' => $base, 'gateway' => $gateway] : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function AddRemoteTargets(array $snapshot): array
    {
        $context = $this->PeerContext();
        if ($context === null || $snapshot === []) {
            return $snapshot;
        }
        try {
            $catalog = $this->peerActionCatalog ??= $this->ReadPeerActionCatalog($context['module']);
            if (($catalog['system'] ?? '') !== $context['system'] || ($catalog['gateway'] ?? 0) !== $context['gateway']
                || !is_array($catalog['devices'] ?? null) || count($catalog['devices']) > 512) {
                return $snapshot;
            }
            foreach ($catalog['devices'] as $key => $entry) {
                if (!is_string($key) || preg_match('/\A(?:0x[a-f0-9]{16}|group\.[1-9][0-9]{0,4})\z/D', $key) !== 1
                    || !is_array($entry) || !is_array($entry['bindings'] ?? null)) {
                    continue;
                }
                $target = str_starts_with($key, 'group.') ? ['type' => 'group', 'id' => (int) substr($key, 6)] : ['type' => 'device', 'id' => $key];
                $target['system'] = $context['system'];
                $entry['remoteTarget'] = $target;
                $entry['name'] = $context['system'] . ' · ' . $entry['name'];
                $entry['triggers'] = $entry['numeric'] = [];
                foreach ($entry['properties'] as &$meta) {
                    $meta['access'] &= 2;
                }
                unset($meta);
                $snapshot['devices'][$this->EditorTargetKey($target)] = $entry;
            }
        } catch (Throwable) {
            $this->DebugEvent('FEHLER', 'Ziele des zweiten Systems fehlen. Dort zuerst die Gerätedaten laden; vorhandene Regeln bleiben erhalten.');
        }
        return $snapshot;
    }

    private function RemoteActionVariable(array $snapshot, string $device, string $property): ?array
    {
        $entry = $snapshot['devices'][$device] ?? [];
        $binding = $entry['bindings'][$property] ?? null;
        if ($binding === null) {
            return null;
        }
        try {
            $scope = $this->ReadZ2MInstanceScope($binding['instance'], true);
            if (($scope['key'] ?? '') !== $device || ($scope['topic'] ?? '') !== ($entry['remoteTarget']['system'] ?? '')
                || !IPS_VariableExists($binding['id'])) {
                return null;
            }
            $object = IPS_GetObject($binding['id']);
            $variable = IPS_GetVariable($binding['id']);
            $action = $variable['VariableCustomAction'] !== 0 ? $variable['VariableCustomAction'] : $variable['VariableAction'];
            return $object['ParentID'] === $binding['instance'] && $object['ObjectIdent'] === $property
                && !($variable['VariableIsLocked'] ?? false) && $action > 1
                && ($variable['VariableCustomAction'] > 1 ? IPS_ScriptExists($action) : IPS_InstanceExists($action)) ? $binding : null;
        } catch (Throwable) {
            return null;
        }
    }
}
