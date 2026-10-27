<?php

declare(strict_types=1);

namespace Z2MAutomations;

use stdClass;

trait AutomationEditor
{
    public function ValidateAutomationDraft(): string
    {
        return $this->TraceModuleCall(__FUNCTION__, __LINE__, fn (): string => $this->ValidateYaml($this->ReadAttributeString('YamlDraft')));
    }

    public function StoreAutomationDraft(): string
    {
        return $this->TraceModuleCall(__FUNCTION__, __LINE__, fn (): string => $this->StoreYaml($this->ReadAttributeString('YamlDraft')));
    }

    public function LoadAutomationEditor(): string
    {
        return $this->TraceModuleCall(__FUNCTION__, __LINE__, fn (): string => $this->StartExtensionWorkflow('editor'));
    }

    public function SelectEditorDevice(string $Side, string $Device): void
    {
        $this->TraceModuleCall(__FUNCTION__, __LINE__, function () use ($Side, $Device): void
        {
            if (!in_array($Side, ['Source', 'Target'], true)) {
                $this->DebugEvent('FEHLER', 'Unbekannte Geräteauswahl verworfen.');
                return;
            }
            $snapshot = $this->EditorSnapshot();
            if (!isset($snapshot['devices'][$Device])) {
                $Device = '';
            }
            $this->WriteAttributeString('Editor' . $Side, $Device);
            $this->UpdateFormField('Editor' . $Side . 'Choice', 'options', json_encode($this->EditorChoices($snapshot, $Device, $Side), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            $this->UpdateFormField('Editor' . $Side . 'Choice', 'value', '');
            $this->DebugEvent('AKTION', 'Geräteauswahl im Editor aktualisiert.', ['Bereich' => $Side, 'GerätGefunden' => $Device !== '']);
        });
    }

    public function ShowYamlEditor(bool $Visible): void
    {
        $this->TraceModuleCall(__FUNCTION__, __LINE__, function () use ($Visible): void
        {
            $this->WriteAttributeBoolean('ShowYaml', $Visible);
            $this->UpdateFormField('YamlInput', 'visible', $Visible);
            $this->DebugEvent('AKTION', 'Erweiterte YAML-Ansicht geändert.', ['Sichtbar' => $Visible]);
        });
    }

    public function CreateSimpleAutomation(string $Name, string $Source, string $Trigger, string $Target, string $Action, string $CurrentDraft = '', bool $CheckDraft = false): bool
    {
        return $this->TraceModuleCall(__FUNCTION__, __LINE__, function () use ($Name, $Source, $Trigger, $Target, $Action, $CurrentDraft, $CheckDraft): bool
        {
            return $this->WithTransportLock(function () use ($Name, $Source, $Trigger, $Target, $Action, $CurrentDraft, $CheckDraft): bool
            {
                $state = $this->RefreshState();
                $snapshot = $this->EditorSnapshot();
                $draftText = $this->ReadAttributeString('EditorDraft');
                $draft = $draftText === '' ? null : json_decode($draftText, true, 32, JSON_THROW_ON_ERROR);
                $error = '';
                if ($draft === null || $snapshot === []) {
                    $error = 'editor_unavailable';
                } elseif (!$state['extensionConfirmed'] || $state['workflow'] !== null || $draft['scope'] !== $state['scope']
                    || $draft['session'] !== $state['extension']['session'] || $draft['baseRevision'] !== $state['extension']['storedRevision']
                    || $draft['baseChecksum'] !== $state['extension']['checksum'] || $draft['draftChecksum'] !== hash('sha256', $this->ReadAttributeString('YamlDraft'))
                    || (($CheckDraft || $CurrentDraft !== '') && hash('sha256', $CurrentDraft) !== $draft['draftChecksum'])) {
                    $error = 'editor_changed';
                }
                $source = $this->FindEditorChoice($snapshot, $Source, $Trigger, 'Source');
                $target = $this->FindEditorChoice($snapshot, $Target, $Action, 'Target');
                $name = trim($Name);
                if ($error === '' && ($name === '' || strlen($name) > 480 || preg_match('//u', $name) !== 1 || preg_match_all('/./us', $name) > 120
                    || preg_match('/[\x00-\x1F\x7F]/', $name) || $source === null || $target === null
                    || count($draft['configuration']['automations']) >= 100)) {
                    $error = 'editor_invalid';
                }
                if ($error !== '') {
                    $state['lastError'] = $error;
                    $this->DebugEvent('FEHLER', 'Automation nicht zum Entwurf hinzugefügt.', ['Grund' => $error]);
                    $this->SaveState($state);
                    return false;
                }
                $draft['configuration']['revision'] = $draft['baseRevision'] + 1;
                $draft['configuration']['automations'][] = ['id' => 'rule_' . bin2hex(random_bytes(8)), 'name' => $name, 'enabled' => true,
                    'triggers'                                   => [['type' => 'device', 'device' => $Source, 'property' => $source['property'], 'operator' => 'eq', 'value' => $source['value']]],
                    'conditions'                                 => [], 'actions' => [['type' => 'set', 'target' => ['type' => 'device', 'id' => $Target],
                        'values'                                                              => [$target['property'] => $target['value']]]]];
                $yaml = json_encode($draft['configuration'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
                if (!ExtensionContract::ValidYamlText($yaml)) {
                    $state['lastError'] = 'editor_invalid';
                    $this->SaveState($state);
                    return false;
                }
                $this->SaveEditorDraft($draft, $yaml);
                $state['validation'] = null;
                $state['lastError'] = '';
                $state['operation'] = ['kind' => 'editor', 'status' => 'draft_created', 'transaction' => ''];
                $this->DebugEvent(
                    'AKTION',
                    'Automation im lokalen Entwurf ergänzt; Z2M noch nicht verändert.',
                    ['Automationen' => count($draft['configuration']['automations']), 'Revision' => $draft['configuration']['revision']]
                );
                $this->SaveState($state);
                return true;
            });
        });
    }

    private function ReceiveEditorData(array &$state, stdClass $data, array $target): void
    {
        $metadata = ExtensionContract::Status($data);
        $configuration = $data->configuration ?? null;
        if ($metadata === null || $metadata !== $target || ($data->editor_version ?? null) !== 1 || !is_array($data->devices ?? null)
            || count($data->devices) > 256 || !$configuration instanceof stdClass || ($configuration->schema_version ?? null) !== 1
            || ($configuration->revision ?? null) !== $metadata['storedRevision'] || !is_array($configuration->automations ?? null)
            || count($configuration->automations) !== $metadata['automationCount'] || !in_array($metadata['storage'], ['ok', 'empty'], true)) {
            $this->FinishExtensionWorkflow($state, 'error', 'invalid_response');
            return;
        }
        $devices = [];
        foreach ($data->devices as $device) {
            if (!$device instanceof stdClass || !is_string($device->id ?? null) || preg_match('/\A0x[a-f0-9]{16}\z/D', $device->id) !== 1
                || !is_string($device->name ?? null) || strlen($device->name) > 480 || isset($devices[$device->id])) {
                $this->FinishExtensionWorkflow($state, 'error', 'invalid_response');
                return;
            }
            $entry = ['name' => $device->name];
            foreach (['triggers', 'actions'] as $kind) {
                if (!is_array($device->$kind ?? null) || count($device->$kind) > 64) {
                    $this->FinishExtensionWorkflow($state, 'error', 'invalid_response');
                    return;
                }
                $entry[$kind] = [];
                foreach ($device->$kind as $option) {
                    if (!$option instanceof stdClass || !is_string($option->property ?? null)
                        || preg_match('/\A[A-Za-z][A-Za-z0-9_]{0,63}\z/D', $option->property) !== 1
                        || in_array($option->property, ['constructor', 'prototype', '__proto__'], true) || !property_exists($option, 'value')
                        || !(is_scalar($option->value) || $option->value === null) || (is_string($option->value) && strlen($option->value) > 1024)) {
                        $this->FinishExtensionWorkflow($state, 'error', 'invalid_response');
                        return;
                    }
                    $value = ['property' => $option->property, 'value' => $option->value];
                    $key = hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
                    $entry[$kind][$key] = $value;
                }
            }
            $devices[$device->id] = $entry;
        }
        // JSON is a valid YAML document; the authoritative schema check stays in Z2M.
        $yaml = $metadata['storedRevision'] === 0
            ? json_encode($configuration, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"
            : ($data->yaml ?? null);
        if (!is_string($yaml) || strlen($yaml) > ExtensionContract::MAX_YAML_BYTES
            || ($metadata['storedRevision'] > 0 && hash('sha256', $yaml) !== $metadata['checksum'])) {
            $this->FinishExtensionWorkflow($state, 'error', 'invalid_response');
            return;
        }
        $this->WriteAttributeString('EditorSnapshot', json_encode(['scope' => $state['scope'], 'devices' => $devices], JSON_THROW_ON_ERROR));
        $this->WriteAttributeString('EditorSource', '');
        $this->WriteAttributeString('EditorTarget', '');
        $draft = ['scope'  => $state['scope'], 'session' => $metadata['session'], 'baseRevision' => $metadata['storedRevision'],
            'baseChecksum' => $metadata['checksum'], 'configuration' => json_decode(json_encode($configuration, JSON_THROW_ON_ERROR), true, 32, JSON_THROW_ON_ERROR)];
        $this->SaveEditorDraft($draft, $yaml);
        $state['validation'] = null;
        $this->UpdateFormField('EditorSource', 'options', json_encode($this->EditorDevices(['devices' => $devices], 'Source'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $this->UpdateFormField('EditorTarget', 'options', json_encode($this->EditorDevices(['devices' => $devices], 'Target'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        foreach (['EditorSource', 'EditorTarget', 'EditorSourceChoice', 'EditorTargetChoice'] as $field) {
            $this->UpdateFormField($field, 'value', '');
        }
        $this->DebugEvent(
            'EMPFANG',
            'Geräte und gespeicherte Automationen für den Editor übernommen.',
            ['Geräte' => count($devices), 'Automationen' => $metadata['automationCount']]
        );
        $this->FinishExtensionWorkflow($state, 'editor_loaded');
    }

    private function SaveEditorDraft(array $draft, string $yaml): void
    {
        $draft['draftChecksum'] = hash('sha256', $yaml);
        $this->WriteAttributeString('EditorDraft', json_encode($draft, JSON_THROW_ON_ERROR));
        $this->WriteAttributeString('YamlDraft', $yaml);
        $this->UpdateFormField('YamlInput', 'value', $yaml);
        $this->UpdateFormField('EditorPreview', 'caption', sprintf($this->Translate('Draft contains %d automations. Z2M is unchanged until storing.'), count($draft['configuration']['automations'])));
        $this->UpdateFormField('EditorRules', 'values', json_encode($this->EditorRuleRows($draft), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    private function EditorSnapshot(): array
    {
        $json = $this->ReadAttributeString('EditorSnapshot');
        return $json === '' ? [] : json_decode($json, true, 32, JSON_THROW_ON_ERROR);
    }

    private function EditorDevices(array $snapshot, string $side): array
    {
        $options = [['caption' => $this->Translate('Choose device'), 'value' => '']];
        foreach ($snapshot['devices'] ?? [] as $id => $device) {
            if ($device[$side === 'Source' ? 'triggers' : 'actions'] !== []) {
                $options[] = ['caption' => $device['name'] . ' (' . $id . ')', 'value' => $id];
            }
        }
        return $options;
    }

    private function EditorChoices(array $snapshot, string $id, string $side): array
    {
        $options = [['caption' => $this->Translate('Choose event or action'), 'value' => '']];
        foreach ($snapshot['devices'][$id][$side === 'Source' ? 'triggers' : 'actions'] ?? [] as $key => $choice) {
            $property = match ($choice['property']) {
                'action' => 'Button event', 'state' => 'State', 'occupancy' => 'Motion', 'contact' => 'Contact', default => $choice['property']
            };
            $value = match ($choice['value']) {
                'ON' => 'On', 'OFF' => 'Off', 'single' => 'Single press', 'double' => 'Double press', 'hold' => 'Hold', 'release' => 'Release',
                true => 'Yes', false => 'No', default => json_encode($choice['value'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
            };
            $options[] = ['caption' => $this->Translate($property) . ': ' . $this->Translate($value), 'value' => $key];
        }
        return $options;
    }

    private function FindEditorChoice(array $snapshot, string $id, string $key, string $side): ?array
    {
        return $snapshot['devices'][$id][$side === 'Source' ? 'triggers' : 'actions'][$key] ?? null;
    }

    private function FillEditorForm(array &$form, array $state): void
    {
        $snapshot = $this->EditorSnapshot();
        if (($snapshot['scope'] ?? null) !== $state['scope']) {
            $snapshot = [];
        }
        foreach ($form['actions'] as &$field) {
            if (($field['name'] ?? '') === 'EditorRules' && $snapshot !== []) {
                $draft = $this->ReadAttributeString('EditorDraft');
                $field['values'] = $draft === '' ? [] : $this->EditorRuleRows(json_decode($draft, true, 32, JSON_THROW_ON_ERROR));
            }
            if (($field['name'] ?? '') === 'EditorPreview') {
                $draft = $this->ReadAttributeString('EditorDraft');
                if ($draft !== '' && $snapshot !== []) {
                    $configuration = json_decode($draft, true, 32, JSON_THROW_ON_ERROR)['configuration'];
                    $field['caption'] = sprintf($this->Translate('Draft contains %d automations. Z2M is unchanged until storing.'), count($configuration['automations']));
                }
            }
            foreach (['Source', 'Target'] as $side) {
                $selected = $this->ReadAttributeString('Editor' . $side);
                if (($field['name'] ?? '') === 'Editor' . $side) {
                    $field['options'] = $this->EditorDevices($snapshot, $side);
                    $field['value'] = isset($snapshot['devices'][$selected]) ? $selected : '';
                } elseif (($field['name'] ?? '') === 'Editor' . $side . 'Choice') {
                    $field['options'] = $this->EditorChoices($snapshot, $selected, $side);
                }
            }
        }
        unset($field);
    }

    private function EditorRuleRows(array $draft): array
    {
        $snapshot = $this->EditorSnapshot();
        $rows = [];
        foreach ($draft['configuration']['automations'] ?? [] as $rule) {
            if (!is_array($rule) || !is_string($rule['name'] ?? null)) {
                continue;
            }
            $source = is_array($rule['triggers'][0] ?? null) && is_string($rule['triggers'][0]['device'] ?? null) ? $rule['triggers'][0]['device'] : '';
            $target = is_array($rule['actions'][0]['target'] ?? null) && is_string($rule['actions'][0]['target']['id'] ?? null) ? $rule['actions'][0]['target']['id'] : '';
            $rows[] = ['Name' => $rule['name'], 'Source' => $snapshot['devices'][$source]['name'] ?? $this->Translate('Advanced rule'),
                'Target'      => $snapshot['devices'][$target]['name'] ?? $this->Translate('Advanced rule'),
                'Enabled'     => $this->Translate(($rule['enabled'] ?? false) === true ? 'Yes' : 'No')];
        }
        return $rows;
    }
}
