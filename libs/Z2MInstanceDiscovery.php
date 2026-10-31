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

    private function GatewayBaseTopics(): array
    {
        $topics = [];
        foreach ($this->GatewayZ2MInstances() as $scope) {
            $topics[$scope['topic']] = true;
        }
        $names = array_keys($topics);
        sort($names, SORT_STRING);
        return $names;
    }

    private function GatewayTopicFields(): array
    {
        $names = $this->GatewayBaseTopics();
        $current = $this->ReadPropertyString('BaseTopic');
        $options = [];
        // Every native MultiSelect option must bind the same nonempty property set.
        // Keep manual/previous settings representable without inventing a detected topic.
        if (!in_array($current, $names, true)) {
            $options[] = ['caption' => sprintf($this->Translate('Saved base topic: %s (not detected here)'), $current),
                'value'             => [['name' => 'BaseTopic', 'value' => $current]]];
        }
        foreach ($names as $topic) {
            $options[] = ['caption' => $topic, 'value' => [['name' => 'BaseTopic', 'value' => $topic]]];
        }
        $hint = !$this->HasParent() ? 'Connect an MQTT gateway, then select or enter the Z2M base topic.'
            : ($names === [] ? 'No base topic was detected on this gateway. Enter the Z2M base topic manually.'
                : (!in_array($current, $names, true) ? 'The saved base topic was not found on this gateway. Choose a detected topic or enter it manually, then apply the settings.'
                    : (count($names) > 1 ? 'Several base topics were found. Select the intended Z2M system; the gateway alone does not determine its topic.'
                        : 'The saved base topic matches the devices on this gateway.')));
        return ['options' => $options, 'enabled' => $names !== [], 'hint' => $this->Translate($hint)];
    }

    private function FillGatewayTopics(array &$form): void
    {
        $fields = $this->GatewayTopicFields();
        foreach ($this->EditorFormFields($form['elements']) as &$field) {
            if (($field['name'] ?? '') === 'DetectedBaseTopic') {
                unset($field['value']);
                foreach (['options', 'enabled'] as $parameter) {
                    $field[$parameter] = $fields[$parameter];
                }
            } elseif (($field['name'] ?? '') === 'GatewayTopicInfo') {
                $field['caption'] = $fields['hint'];
            }
        }
        unset($field);
        $this->DebugEvent('AKTION', 'Basistopics aus Z2M-Instanzen am eigenen MQTT-Gateway ermittelt; Einstellungen unverändert.', ['Gateway' => $this->GetParentID(), 'GefundeneTopics' => count($this->GatewayBaseTopics())]);
    }

    private function RefreshGatewayTopicFields(): void
    {
        $fields = $this->GatewayTopicFields();
        // MultiSelect resolves its selection from the named properties. An explicit
        // JSON-encoded value is treated as a scalar string by the native console.
        foreach (['options', 'enabled'] as $parameter) {
            $value = is_array($fields[$parameter]) ? json_encode($fields[$parameter], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : $fields[$parameter];
            $this->UpdateFormField('DetectedBaseTopic', $parameter, $value);
        }
        $this->UpdateFormField('GatewayTopicInfo', 'caption', $fields['hint']);
        $this->DebugEvent('AKTION', 'Topic-Auswahl nach Verbindungs- oder Einstellungswechsel aktualisiert; gespeichertes Basistopic und Entwurf unverändert.', ['Gateway' => $this->GetParentID(), 'GefundeneTopics' => count($this->GatewayBaseTopics())]);
    }
}
