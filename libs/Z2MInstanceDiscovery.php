<?php

declare(strict_types=1);

namespace Z2MAutomations;

use Throwable;

trait Z2MInstanceDiscovery
{
    private const Z2M_MODULE_TYPES = [
        '{E5BB36C6-A70B-EB23-3716-9151A09AC8A2}' => 'device',
        '{11BF3773-E940-469B-9DD7-FB9ACD7199A2}' => 'group',
        '{00160D82-9E2F-D1BD-6D0B-952F945332C5}' => 'bridge'
    ];

    private ?array $gatewayZ2MInstances = null;

    private function ReadZ2MInstanceScope(int $id, bool $allowPeer = false): ?array
    {
        // A variable may belong to a category, script or entirely different module.
        // Existence and module identity must be checked before any configuration read.
        if ($id <= 0 || !IPS_InstanceExists($id)) {
            return null;
        }
        try {
            $info = IPS_GetInstance($id);
            if (!is_array($info)) {
                return null;
            }
            $type = self::Z2M_MODULE_TYPES[$info['ModuleInfo']['ModuleID'] ?? ''] ?? null;
            $gateway = $this->GetParentID();
            $peer = $allowPeer ? $this->PeerContext() : null;
            $peerGateway = $peer !== null && ($info['ConnectionID'] ?? 0) === $peer['gateway'];
            if ($type === null || $gateway <= 0 || !IPS_InstanceExists($gateway) || (($info['ConnectionID'] ?? 0) !== $gateway && !$peerGateway)) {
                return null;
            }
            $configuration = json_decode(IPS_GetConfiguration($id), true, 32, JSON_THROW_ON_ERROR);
            $topic = $configuration['MQTTBaseTopic'] ?? null;
            if (!is_string($topic) || !Z2MProtocol::IsValidBaseTopic($topic)) {
                return null;
            }
            // A common MQTT parent does not identify the Z2M system: match its topic as well.
            $remote = $peerGateway && $topic === $peer['system'];
            if (($info['ConnectionID'] ?? 0) !== $gateway && !$remote) {
                return null;
            }
            $key = '';
            if ($type === 'device') {
                $key = $configuration['IEEE'] ?? null;
                if (!is_string($key) || preg_match('/\A0x[a-f0-9]{16}\z/D', $key) !== 1) {
                    return null;
                }
            } elseif ($type === 'group') {
                $group = $configuration['GroupId'] ?? null;
                if (!is_int($group) || $group < 1 || $group > 65535) {
                    return null;
                }
                $key = 'group.' . $group;
            }
            if ($remote) {
                if ($topic !== $peer['system'] || $type === 'bridge') {
                    return null;
                }
                $key = 'remote.' . hash('sha256', $topic) . '.' . $key;
            }
            return ['type' => $type, 'key' => $key, 'topic' => $topic];
        } catch (Throwable) {
            return null;
        }
    }

    private function GatewayZ2MInstances(): array
    {
        if ($this->gatewayZ2MInstances !== null) {
            return $this->gatewayZ2MInstances;
        }
        $instances = [];
        foreach (array_keys(self::Z2M_MODULE_TYPES) as $module) {
            foreach (array_slice(IPS_GetInstanceListByModuleID($module), 0, 512) as $id) {
                $scope = $this->ReadZ2MInstanceScope($id);
                if ($scope !== null) {
                    $instances[$id] = $scope;
                }
            }
        }
        return $this->gatewayZ2MInstances = $instances;
    }

    private function FillGatewayTopics(array &$form): void
    {
        $topics = [];
        foreach ($this->GatewayZ2MInstances() as $scope) {
            $topics[$scope['topic']] = true;
        }
        $names = array_keys($topics);
        sort($names, SORT_STRING);
        // Native multi-select changes only the form's BaseTopic field, not the gateway.
        // The editable text field remains available for installations without devices.
        $options = [['caption' => $this->Translate('Choose a detected base topic'), 'value' => []]];
        foreach ($names as $topic) {
            $options[] = ['caption' => $topic, 'value' => [['name' => 'BaseTopic', 'value' => $topic]]];
        }
        foreach ($this->EditorFormFields($form['elements']) as &$field) {
            if (($field['name'] ?? '') === 'DetectedBaseTopic') {
                $field['options'] = $options;
                $field['enabled'] = $names !== [];
            }
        }
        unset($field);
        $this->DebugEvent('AKTION', 'Basistopics aus Z2M-Instanzen am eigenen MQTT-Gateway ermittelt; Einstellungen unverändert.', ['Gateway' => $this->GetParentID(), 'GefundeneTopics' => count($names)]);
    }
}
