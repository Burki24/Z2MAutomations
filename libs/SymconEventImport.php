<?php

declare(strict_types=1);

namespace Z2MAutomations;

use Throwable;

trait SymconEventImport
{
    public function ScanEventImports(): bool
    {
        return $this->TraceModuleCall(__FUNCTION__, __LINE__, function (): bool
        {
            $this->SetBuffer('EventImportPreview', '');
            $rows = $options = [];
            $snapshot = $this->EditorSnapshot();
            $ids = $this->ImportEventIDs();
            foreach (array_slice($ids, 0, 4096) as $id) {
                $record = $this->ReadImportEvent($id);
                if ($record === null || $this->ImportVariableBinding($snapshot, $record['event']['TriggerVariableID'] ?? 0, 'Source') === null) {
                    continue;
                }
                $result = $this->EvaluateImportEvent($id, $record, $snapshot);
                $rows[] = ['Name' => $record['name'], 'Result' => $this->Translate($result['reason'])];
                $options[] = ['caption' => $record['name'], 'value' => $id];
                if (count($rows) >= 256) {
                    break;
                }
            }
            $report = ['rows' => $rows, 'options' => $options];
            $this->SetBuffer('EventImportReport', json_encode($report, JSON_THROW_ON_ERROR));
            $this->RefreshEventImportFields();
            $message = $snapshot === [] ? 'Load devices and stored automations before checking events.'
                : (count($ids) > 4096 || count($rows) >= 256 ? 'Search limit reached. Only the listed events were checked.' : 'Select an event to preview its import.');
            $this->UpdateFormField('ImportMessage', 'caption', $this->Translate($message));
            $this->DebugEvent('AKTION', 'Symcon-Ereignisse lesend geprüft; keine Ereignisse oder Geräte verändert.', ['EreignisseGeprüft' => min(count($ids), 4096), 'Zugeordnet' => count($rows)]);
            return $snapshot !== [];
        });
    }

    public function PreviewEventImport(int $EventID): bool
    {
        return $this->TraceModuleCall(__FUNCTION__, __LINE__, function () use ($EventID): bool
        {
            $this->SetBuffer('EventImportPreview', '');
            $result = $this->EvaluateImportEvent($EventID, $this->ReadImportEvent($EventID), $this->EditorSnapshot());
            $preview = ['event' => $EventID, 'result' => $result, 'token' => $result['rule'] === null ? '' : bin2hex(random_bytes(16))];
            $this->SetBuffer('EventImportPreview', json_encode($preview, JSON_THROW_ON_ERROR));
            $this->RefreshEventImportFields();
            $this->DebugEvent($result['rule'] === null ? 'FEHLER' : 'AKTION', 'Importvorschau geprüft; Originalereignis bleibt unverändert.', ['Übertragbar' => $result['rule'] !== null, 'Grund' => $result['reason']]);
            return $result['rule'] !== null;
        });
    }

    public function ImportSymconEvent(int $EventID, string $Token, string $CurrentDraft = '', bool $CheckDraft = false): bool
    {
        return $this->TraceModuleCall(__FUNCTION__, __LINE__, fn (): bool => $this->WithTransportLock(function () use ($EventID, $Token, $CurrentDraft, $CheckDraft): bool
        {
            $state = $this->RefreshState();
            $draft = $this->ReadEditorDraft();
            $preview = $this->EventImportBuffer('EventImportPreview');
            $result = $this->EvaluateImportEvent($EventID, $this->ReadImportEvent($EventID), $this->EditorSnapshot());
            $error = $this->EditorContextError($state, $draft, $CurrentDraft, $CheckDraft, false);
            if ($error !== '' || $result['rule'] === null || $Token === '' || ($preview['event'] ?? null) !== $EventID
                || !hash_equals($preview['token'] ?? '', $Token) || ($preview['result'] ?? null) !== $result
                || count($draft['configuration']['automations'] ?? []) >= 100) {
                $this->UpdateFormField('ImportMessage', 'caption', $this->Translate('Import rejected. Preview the current event again and check the draft.'));
                $this->DebugEvent('FEHLER', 'Ereignisimport abgewiesen; Vorschau, Zuordnung oder Entwurf nicht mehr gültig.', ['Grund' => $error !== '' ? $error : $result['reason']]);
                return false;
            }
            if ($this->EditorRuleIndex($draft, $result['rule']['id']) !== null) {
                $this->UpdateFormField('ImportMessage', 'caption', $this->Translate('This event is already present in the draft. Edit the imported rule instead.'));
                $this->DebugEvent('FEHLER', 'Ereignis bereits importiert; vorhandene Regel nicht ersetzt.');
                return false;
            }
            $draft['configuration']['automations'][] = $result['rule'];
            if (!$this->CommitEditorChange($state, $draft, 'draft_created', $result['rule']['id'])) {
                return false;
            }
            $this->UpdateFormField('EditorDeviceSection', 'expanded', true);
            $this->SetBuffer('EventImportPreview', '');
            $this->RefreshEventImportFields();
            $this->UpdateFormField('ImportMessage', 'caption', $this->Translate('Imported as a disabled draft. The Symcon event is unchanged. Review it and disable the original event before activation in Z2M.'));
            $this->DebugEvent('AKTION', 'Symcon-Ereignis als deaktivierte Regel in den lokalen Entwurf übernommen; Original, Z2M und Geräte unverändert.');
            return true;
        }));
    }

    protected function ImportEventIDs(): array
    {
        return IPS_GetEventListByType(0);
    }

    protected function ReadImportEvent(int $id): ?array
    {
        try {
            return IPS_EventExists($id) ? ['name' => IPS_GetName($id), 'event' => IPS_GetEvent($id)] : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function EvaluateImportEvent(int $id, ?array $record, array $snapshot): array
    {
        $reject = static fn (string $reason): array => ['reason' => $reason, 'rule' => null];
        if ($record === null) {
            return $reject('Event no longer exists.');
        }
        $event = $record['event'];
        if (($event['EventType'] ?? -1) !== 0 || ($event['TriggerType'] ?? -1) !== 4
            || ($event['TriggerSubsequentExecution'] ?? false) !== true) {
            return $reject('Only specific-value triggers with repeated execution are currently supported.');
        }
        if (($event['EventLimit'] ?? -1) !== 0 || ($event['EventConditions'] ?? null) !== [] || ($event['EventScript'] ?? '') !== '') {
            return $reject('Execution limits, additional conditions and PHP scripts cannot yet be imported.');
        }
        $source = $this->ImportVariableBinding($snapshot, $event['TriggerVariableID'] ?? 0, 'Source');
        $parameters = $event['EventActionParameters'] ?? [];
        $target = $this->ImportVariableBinding($snapshot, $parameters['TARGET'] ?? 0, 'Target');
        if ($source === null || $target === null) {
            return $reject('Source or target cannot be uniquely assigned to this Z2M catalog.');
        }
        $trigger = $this->ImportVariableValue($snapshot, $source, $event['TriggerValue'] ?? null, 'Source');
        if ($trigger === null) {
            return $reject('The trigger value cannot be mapped without conversion loss.');
        }
        $actionID = strtoupper($event['EventActionID'] ?? '');
        $toggle = $actionID === '{EF078D00-FBD5-8857-F5DC-F5B54B107DD3}';
        // Known native action contracts only; never execute or inspect user code.
        $setActions = ['{46B65CA6-3098-4982-9A6C-B89DADBB0A96}', '{A4C53C8D-795E-403A-9EEC-CC0D71E89A20}',
            '{A4D52B67-BE4B-4AD0-964F-B9BA2556AAB0}', '{FCE37F48-DA3F-45DD-AC77-71343792CC2D}'];
        $keys = array_keys($parameters);
        sort($keys);
        if ((!$toggle && !in_array($actionID, $setActions, true)) || $keys !== ($toggle ? ['TARGET'] : ['TARGET', 'VALUE'])) {
            return $reject('This action or its parameters are not supported by the importer.');
        }
        $value = $toggle ? ($target['type'] === 0 ? 'TOGGLE' : null)
            : $this->ImportVariableValue($snapshot, $target, $parameters['VALUE'], 'Target');
        $targetMeta = $this->EditorProperties($snapshot, $target['key'], 'Target')[$target['property']];
        if ($toggle && (($targetMeta['type'] ?? '') !== 'binary'
            || preg_match('/\Astate(?:_[A-Za-z0-9_]+)?\z/D', $target['property']) !== 1)) {
            $value = null;
        }
        if ($value === null || !$this->EditorValueAllowed($snapshot, $target['key'], $target['property'], $value, 'Target')) {
            return $reject('The target value or toggle command is not supported by this property.');
        }
        if (!$this->ValidEditorName($record['name'])) {
            return $reject('The event name is not a valid automation name.');
        }
        return ['reason'  => 'Supported. Imported rules start disabled; the original event remains unchanged.',
            'fingerprint' => hash('sha256', json_encode([$record, $source, $target, $snapshot['scope'] ?? ''], JSON_THROW_ON_ERROR)),
            'rule'        => ['id' => 'import_' . substr(hash('sha256', $this->InstanceID . ':' . $id), 0, 32), 'name' => $record['name'], 'enabled' => false,
                'triggers'         => [['type' => 'device', 'device' => $source['key'], 'property' => $source['property'], 'operator' => 'eq', 'value' => $trigger]],
                'conditions'       => [], 'actions' => [$this->BuildEditorAction($target['key'], ['property' => $target['property'], 'value' => $value])]],
            'summary' => $snapshot['devices'][$source['key']]['name'] . ' / ' . $source['property'] . ' = ' . $this->EditorValueLabel($trigger)
                . ' → ' . $snapshot['devices'][$target['key']]['name'] . ' / ' . $target['property'] . ': ' . $this->EditorValueLabel($value)];
    }

    private function ImportVariableBinding(array $snapshot, int $id, string $side): ?array
    {
        try {
            if (!IPS_VariableExists($id)) {
                return null;
            }
            $object = IPS_GetObject($id);
            $variable = IPS_GetVariable($id);
            $instance = $object['ParentID'];
            $module = IPS_GetInstance($instance)['ModuleInfo']['ModuleID'];
            if (IPS_GetProperty($instance, 'MQTTBaseTopic') !== $this->ReadPropertyString('BaseTopic')) {
                return null;
            }
            if ($module === '{E5BB36C6-A70B-EB23-3716-9151A09AC8A2}') {
                $key = IPS_GetProperty($instance, 'IEEE');
            } elseif ($module === '{11BF3773-E940-469B-9DD7-FB9ACD7199A2}' && $side === 'Target') {
                $key = 'group.' . IPS_GetProperty($instance, 'GroupId');
            } else {
                return null;
            }
            $property = $object['ObjectIdent'];
            if (!isset($this->EditorProperties($snapshot, $key, $side)[$property])) {
                return null;
            }
            if ($side === 'Target') {
                $binding = $this->EditorActionVariable($snapshot, $key, $property);
                if (($binding['id'] ?? null) !== $id || $variable['VariableCustomAction'] !== 0 || $variable['VariableAction'] !== $instance) {
                    return null;
                }
            } elseif (($this->EditorActionVariables()[$key][$property]['id'] ?? null) !== $id) {
                return null;
            }
            return ['key' => $key, 'property' => $property, 'type' => $variable['VariableType'], 'variable' => $id];
        } catch (Throwable) {
            return null;
        }
    }

    private function ImportVariableValue(array $snapshot, array $binding, mixed $value, string $side): mixed
    {
        // Numeric Symcon variables may use module-specific scaling (e.g. brightness).
        // Import only proven boolean/string mappings until those contracts are explicit.
        if (!in_array($binding['type'], [0, 3], true)) {
            return null;
        }
        $meta = $this->EditorProperties($snapshot, $binding['key'], $side)[$binding['property']];
        if ($binding['type'] === 0 && is_bool($value) && $meta['type'] === 'binary') {
            if (in_array(true, $meta['values'], true) && in_array(false, $meta['values'], true)) {
                return $value;
            }
            if (in_array('ON', $meta['values'], true) && in_array('OFF', $meta['values'], true)) {
                return $value ? 'ON' : 'OFF';
            }
            return null;
        }
        return $this->EditorValueAllowed($snapshot, $binding['key'], $binding['property'], $value, $side) ? $value : null;
    }

    private function EventImportBuffer(string $name): array
    {
        $text = $this->GetBuffer($name);
        return $text === '' ? [] : json_decode($text, true, 32, JSON_THROW_ON_ERROR);
    }

    private function EventImportFields(): array
    {
        $report = $this->EventImportBuffer('EventImportReport');
        $preview = $this->EventImportBuffer('EventImportPreview');
        $result = $preview['result'] ?? [];
        return ['ImportEvents' => ['values' => $report['rows'] ?? []],
            'ImportEventID'    => ['options' => array_merge([['caption' => $this->Translate('Choose event'), 'value' => 0]], $report['options'] ?? []), 'value' => $preview['event'] ?? 0],
            'ImportToken'      => ['value' => $preview['token'] ?? ''],
            'ImportMessage'    => ['caption' => isset($result['reason']) ? $this->Translate($result['reason']) . (isset($result['summary']) ? "\n" . $result['summary'] : '') : $this->Translate('Check existing Symcon events, then select one for preview.')],
            'ImportButton'     => ['enabled' => ($preview['token'] ?? '') !== '']];
    }

    private function RefreshEventImportFields(): void
    {
        foreach ($this->EventImportFields() as $name => $changes) {
            foreach ($changes as $parameter => $value) {
                $this->UpdateFormField($name, $parameter, is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : $value);
            }
        }
    }

    private function FillEventImportForm(array &$form): void
    {
        $fields = $this->EventImportFields();
        foreach ($this->EditorFormFields($form['actions']) as &$field) {
            $field = array_replace($field, $fields[$field['name'] ?? ''] ?? []);
        }
        unset($field);
    }
}
