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
            $this->SetBuffer('EventImportNotice', '');
            $rows = $options = [];
            $snapshot = $this->EditorSnapshot();
            if (!$this->ImportCatalogMatchesContext($snapshot)) {
                $snapshot = [];
            }
            $ids = $this->ImportEventIDs();
            foreach (array_slice($ids, 0, 4096) as $id) {
                $record = $this->ReadImportEvent($id);
                if ($record === null || !$this->ImportSourceInScope($record['event']['TriggerVariableID'] ?? 0)) {
                    continue;
                }
                $result = $this->EvaluateImportEvent($id, $record, $snapshot);
                $device = $this->ImportSourceDevice($record['event']['TriggerVariableID']);
                $rows[] = ['Name' => $this->ImportEventName($id, $record), 'Device' => $device['name'], 'Instance' => $device['id'], 'Result' => $this->ImportAssessmentText($result['reason'], $result['arguments'] ?? [])];
                $options[] = ['caption' => $this->ImportEventName($id, $record) . ' — ' . $device['name'] . ' (' . $device['id'] . ')', 'value' => $id];
                if (count($rows) >= 256) {
                    break;
                }
            }
            $report = ['context' => $this->EventImportContext(), 'rows' => $rows, 'options' => $options];
            $this->SetBuffer('EventImportReport', json_encode($report, JSON_THROW_ON_ERROR));
            $this->RefreshEventImportFields();
            $message = $snapshot === [] ? $this->ImportDeviceDataHelp()
                : (count($ids) > 4096 || count($rows) >= 256 ? 'Search limit reached. Only the listed events were checked.' : 'Select an event to preview its import.');
            $this->UpdateFormField('ImportMessage', 'caption', $this->Translate($message));
            $this->DebugEvent('AKTION', 'Symcon-Ereignisse lesend geprüft; keine Ereignisse oder Geräte verändert.', ['Gateway' => $this->GetParentID(), 'Basistopic' => $this->ReadPropertyString('BaseTopic'), 'EreignisseGeprüft' => min(count($ids), 4096), 'Zugeordnet' => count($rows)]);
            return $this->HasParent();
        });
    }

    public function LoadImportDevices(int $EventID, bool $Confirmed = false): string
    {
        return $this->TraceModuleCall(__FUNCTION__, __LINE__, function () use ($EventID, $Confirmed): string
        {
            if (!$Confirmed) {
                $this->DebugEvent('AKTION', 'Laden der Import-Gerätedaten ohne Bestätigung nicht gestartet; Entwurf bleibt erhalten.');
                return '';
            }
            $this->SetBuffer('EventImportPreview', '');
            $this->SetBuffer('EventImportNotice', '');
            $this->UpdateFormField('ImportToken', 'value', '');
            $this->UpdateFormField('ImportButton', 'enabled', false);
            $this->UpdateFormField('ImportEventID', 'enabled', false);
            $this->UpdateFormField('ImportLoadButton', 'enabled', false);
            $this->UpdateFormField('ImportMessage', 'caption', $this->Translate('Loading device data from Z2M. The selected event will be checked after the response.'));
            $this->DebugEvent('AKTION', 'Aktuelle Gerätedaten für den Import angefordert; vorhandener Entwurf bleibt erhalten.', ['Gateway' => $this->GetParentID(), 'Basistopic' => $this->ReadPropertyString('BaseTopic')]);
            $request = $this->StartExtensionWorkflow('editor', '', '', false, false, $EventID, true);
            if ($request === '') {
                $state = $this->WithTransportLock(fn (): array => $this->RefreshState());
                $this->FinishImportDeviceLoad($EventID, 'error', $state['workflow'] !== null ? 'busy' : ($state['lastError'] ?: 'busy'), $state);
            }
            return $request;
        });
    }

    public function PreviewEventImport(int $EventID): bool
    {
        return $this->TraceModuleCall(__FUNCTION__, __LINE__, function () use ($EventID): bool
        {
            $this->SetBuffer('EventImportPreview', '');
            $this->SetBuffer('EventImportNotice', '');
            $result = $this->EvaluateImportEvent($EventID, $this->ReadImportEvent($EventID), $this->EditorSnapshot());
            $preview = ['context' => $this->EventImportContext(), 'event' => $EventID, 'result' => $result, 'token' => $result['rule'] === null ? '' : bin2hex(random_bytes(16))];
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
                || ($preview['context'] ?? null) !== $this->EventImportContext()
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
            return IPS_EventExists($id) && IPS_ObjectExists($id)
                ? ['name' => IPS_GetName($id), 'parent' => IPS_GetObject($id)['ParentID'], 'event' => IPS_GetEvent($id)] : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function FinishImportDeviceLoad(int $eventID, string $status, string $error, array $state): void
    {
        if ($error === '' && $status === 'editor_loaded') {
            $this->ScanEventImports();
            if ($eventID > 0) {
                $this->PreviewEventImport($eventID);
            }
            $this->DebugEvent('AKTION', 'Import-Gerätedaten geladen; Ereignisübersicht und ausgewählte Vorschau neu geprüft.');
        } else {
            $this->SetBuffer('EventImportNotice', json_encode(['context' => $this->EventImportContext(), 'error' => $error], JSON_THROW_ON_ERROR));
            $this->DebugEvent('FEHLER', 'Import-Gerätedaten nicht geladen; keine neue Importfreigabe erteilt.', ['Grund' => $error]);
        }
        $this->RefreshEventImportFields($state);
    }

    private function EvaluateImportEvent(int $id, ?array $record, array $snapshot): array
    {
        $reject = static fn (string $reason, array $arguments = []): array => ['reason' => $reason, 'arguments' => $arguments, 'rule' => null];
        if (!$this->ImportCatalogMatchesContext($snapshot)) {
            return $reject('Device data not loaded yet.');
        }
        if ($record === null) {
            return $reject('Event no longer exists.');
        }
        $event = $record['event'];
        if (($event['EventType'] ?? -1) !== 0 || ($event['TriggerType'] ?? -1) !== 4
            || !is_bool($event['TriggerSubsequentExecution'] ?? null)) {
            return $reject('Only specific-value triggers are currently supported.');
        }
        if (($event['EventLimit'] ?? -1) !== 0 || ($event['EventConditions'] ?? null) !== [] || ($event['EventScript'] ?? '') !== '') {
            return $reject('Execution limits, additional conditions and PHP scripts cannot yet be imported.');
        }
        $failure = [];
        $source = $this->ImportVariableBinding($snapshot, $event['TriggerVariableID'] ?? 0, 'Source', $failure);
        if ($source === null) {
            return $reject($failure['reason'], $failure['arguments']);
        }
        $action = $this->ResolveImportAction($record);
        if ($action === null) {
            return $reject('The event action target or nested status-variable action cannot be resolved.');
        }
        $parameters = $action['parameters'];
        $target = $this->ImportVariableBinding($snapshot, $parameters['TARGET'] ?? 0, 'Target', $failure);
        if ($target === null) {
            return $reject($failure['reason'], $failure['arguments']);
        }
        $trigger = $this->ImportVariableValue($snapshot, $source, $event['TriggerValue'] ?? null, 'Source');
        if ($trigger === null) {
            return $reject('The trigger value cannot be mapped without conversion loss.');
        }
        $actionID = $action['id'];
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
        $name = $this->ImportEventName($id, $record);
        if (!$this->ValidEditorName($name)) {
            return $reject('The event name is not a valid automation name.');
        }
        return ['reason'  => 'Supported. Imported rules start disabled; the original event remains unchanged.',
            'fingerprint' => hash('sha256', json_encode([$record, $source, $target, $snapshot['scope'] ?? ''], JSON_THROW_ON_ERROR)),
            'rule'        => ['id' => 'import_' . substr(hash('sha256', $this->InstanceID . ':' . $id), 0, 32), 'name' => $name, 'enabled' => false,
                'triggers'         => [array_merge(
                    ['type' => 'device', 'device' => $source['key'], 'property' => $source['property'], 'operator' => 'eq', 'value' => $trigger],
                    $event['TriggerSubsequentExecution'] ? [] : ['on_change' => true]
                )],
                'conditions'       => [], 'actions' => [$this->BuildEditorAction($target['key'], ['property' => $target['property'], 'value' => $value])]],
            'summary' => $snapshot['devices'][$source['key']]['name'] . ' / ' . $source['property'] . ' = ' . $this->EditorValueLabel($trigger)
                . ' → ' . $snapshot['devices'][$target['key']]['name'] . ' / ' . $target['property'] . ': ' . $this->EditorValueLabel($value)
                . ' · ' . $this->Translate($event['TriggerSubsequentExecution'] ? 'On every matching report' : 'Only when the value changes')];
    }

    private function ImportEventName(int $id, array $record): string
    {
        return trim($record['name']) === '' ? sprintf($this->Translate('Imported automation %d'), $id) : $record['name'];
    }

    private function ResolveImportAction(array $record): ?array
    {
        $event = $record['event'];
        $parameters = $event['EventActionParameters'] ?? null;
        $id = $event['EventActionID'] ?? null;
        if (!is_array($parameters) || !is_string($id)) {
            return null;
        }
        // Symcon supplies TARGET from the event's parent; it is normally absent
        // from EventActionParameters. Explicit targets remain accepted for exports.
        $target = $record['parent'] ?? $parameters['TARGET'] ?? 0;
        if (!is_int($target) || $target <= 0 || (isset($parameters['TARGET']) && $parameters['TARGET'] !== $target)) {
            return null;
        }
        unset($parameters['TARGET']);
        $id = strtoupper($id);
        if ($id === '{E616C2B2-A827-1712-4AE0-841C57B3DD74}') {
            $keys = array_keys($parameters);
            sort($keys);
            if ($keys !== ['ACTION', 'IDENT'] || !is_string($parameters['IDENT'])
                || preg_match('/\A[A-Za-z][A-Za-z0-9_]{0,63}\z/D', $parameters['IDENT']) !== 1
                || !is_string($parameters['ACTION']) || strlen($parameters['ACTION']) > 4096
                || $this->ReadZ2MInstanceScope($target) === null) {
                return null;
            }
            try {
                $nested = json_decode($parameters['ACTION'], true, 8, JSON_THROW_ON_ERROR);
                $variable = null;
                foreach (IPS_GetChildrenIDs($target) as $child) {
                    if (!IPS_ObjectExists($child) || !IPS_VariableExists($child)) {
                        continue;
                    }
                    if (IPS_GetObject($child)['ObjectIdent'] === $parameters['IDENT']) {
                        if ($variable !== null) {
                            return null;
                        }
                        $variable = $child;
                    }
                }
                if ($variable === null || !is_array($nested) || count($nested) !== 2
                    || !is_string($nested['actionID'] ?? null) || !is_array($nested['parameters'] ?? null)
                    || array_key_exists('TARGET', $nested['parameters'])) {
                    return null;
                }
                $target = $variable;
                $id = strtoupper($nested['actionID']);
                $parameters = $nested['parameters'];
            } catch (Throwable) {
                return null;
            }
        }
        return ['id' => $id, 'parameters' => ['TARGET' => $target] + $parameters];
    }

    private function ImportVariableBinding(array $snapshot, int $id, string $side, array &$failure): ?array
    {
        $label = $this->Translate($side === 'Source' ? 'Trigger variable' : 'Target variable');
        $name = $this->Translate('Variable no longer available');
        $reject = static function (string $reason) use (&$failure, $label, &$name): null
        {
            $failure = ['reason' => $reason, 'arguments' => [$label, $name]];
            return null;
        };
        try {
            if (!IPS_ObjectExists($id) || !IPS_VariableExists($id)) {
                return $reject('%s "%s": The variable no longer exists.');
            }
            $object = IPS_GetObject($id);
            $variable = IPS_GetVariable($id);
            if (!is_array($object) || !is_array($variable)) {
                return $reject('%s "%s": The variable could not be read. Check the event again.');
            }
            $name = $object['ObjectName'];
            $instance = $object['ParentID'];
            if (!IPS_InstanceExists($instance)) {
                return $reject('%s "%s": The variable does not belong to a supported Zigbee2MQTT instance.');
            }
            $info = IPS_GetInstance($instance);
            if (!isset(self::Z2M_MODULE_TYPES[$info['ModuleInfo']['ModuleID'] ?? ''])) {
                return $reject('%s "%s": The variable does not belong to a supported Zigbee2MQTT instance.');
            }
            if (($info['ConnectionID'] ?? 0) !== $this->GetParentID()) {
                return $reject('%s "%s": The device belongs to another MQTT connection.');
            }
            $scope = $this->ReadZ2MInstanceScope($instance);
            if ($scope === null) {
                return $reject('%s "%s": The Zigbee2MQTT instance has an invalid base topic or device identity.');
            }
            if ($scope['topic'] !== $this->ReadPropertyString('BaseTopic')) {
                return $reject('%s "%s": The device uses another Zigbee2MQTT base topic.');
            }
            if ($scope['type'] !== 'device' && !($side === 'Target' && $scope['type'] === 'group')) {
                return $reject('%s "%s": This Zigbee2MQTT instance type is not supported for this use.');
            }
            $key = $scope['key'];
            $property = $object['ObjectIdent'];
            if (!isset($snapshot['devices'][$key])) {
                return $reject('%s "%s": The device is missing from the loaded device data. Load the device data again.');
            }
            $meta = $snapshot['devices'][$key]['properties'][$property] ?? null;
            if ($meta === null) {
                return $reject('%s "%s": This variable is not supported by the import. Its device belongs to the correct MQTT connection and base topic.');
            }
            if (($meta['access'] & ($side === 'Target' ? 2 : 1)) === 0) {
                return $reject($side === 'Target'
                    ? '%s "%s": This property can only be read in Zigbee2MQTT and cannot be used as an action.'
                    : '%s "%s": Zigbee2MQTT does not provide a readable value for this property.');
            }
            if ($side === 'Target') {
                if (($variable['VariableIsLocked'] ?? false) || $variable['VariableCustomAction'] !== 0 || $variable['VariableAction'] !== $instance) {
                    return $reject('%s "%s": The variable is locked or does not use the original Zigbee2MQTT action.');
                }
                $binding = $this->EditorActionVariable($snapshot, $key, $property);
                if (($binding['id'] ?? null) !== $id) {
                    return $reject('%s "%s": Multiple or conflicting Symcon variables refer to the same Zigbee2MQTT property.');
                }
            } elseif (($this->EditorActionVariables()[$key][$property]['id'] ?? null) !== $id) {
                return $reject('%s "%s": Multiple or conflicting Symcon variables refer to the same Zigbee2MQTT property.');
            }
            return ['key' => $key, 'property' => $property, 'type' => $variable['VariableType'], 'variable' => $id];
        } catch (Throwable) {
            return $reject('%s "%s": The variable could not be read. Check the event again.');
        }
    }

    private function ImportAssessmentText(string $reason, array $arguments = []): string
    {
        return vsprintf($this->Translate($reason), $arguments);
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

    private function EventImportContext(): array
    {
        return [$this->GetParentID(), $this->ReadPropertyString('BaseTopic')];
    }

    private function ImportDeviceDataHelp(): string
    {
        return 'Device data are missing for this connection. Click "Load device data for import". The selected event will then be checked again.';
    }

    private function ImportSourceDevice(int $variable): array
    {
        try {
            if (IPS_ObjectExists($variable)) {
                $instance = IPS_GetObject($variable)['ParentID'];
                if ($this->ReadZ2MInstanceScope($instance) !== null) {
                    return ['id' => $instance, 'name' => IPS_GetName($instance)];
                }
            }
        } catch (Throwable) {
            // Concurrent removal must not prevent displaying the remaining search results.
        }
        return ['id' => '', 'name' => $this->Translate('Device no longer available')];
    }

    private function ImportCatalogMatchesContext(array $snapshot): bool
    {
        $scope = json_decode($snapshot['scope'] ?? '', true);
        return is_array($scope) && array_slice($scope, 0, 2) === $this->EventImportContext();
    }

    private function ImportSourceInScope(int $id): bool
    {
        if (!IPS_ObjectExists($id) || !IPS_VariableExists($id)) {
            return false;
        }
        try {
            $object = IPS_GetObject($id);
            $scope = $this->ReadZ2MInstanceScope($object['ParentID']);
            return $scope !== null && in_array($scope['type'], ['device', 'group'], true)
                && $scope['topic'] === $this->ReadPropertyString('BaseTopic');
        } catch (Throwable) {
            return false;
        }
    }

    private function EventImportFields(?array $transport = null): array
    {
        $report = $this->EventImportBuffer('EventImportReport');
        $preview = $this->EventImportBuffer('EventImportPreview');
        if (($report['context'] ?? null) !== $this->EventImportContext()) {
            $report = [];
        }
        if (($preview['context'] ?? null) !== $this->EventImportContext()) {
            $preview = [];
        }
        $result = $preview['result'] ?? [];
        $message = $result['reason'] ?? (!$this->ImportCatalogMatchesContext($this->EditorSnapshot()) ? 'Device data not loaded yet.' : 'Check existing Symcon events, then select one for preview.');
        if ($message === 'Device data not loaded yet.') {
            $message = $this->ImportDeviceDataHelp();
        }
        if ($transport === null) {
            $text = $this->GetBuffer('TransportState');
            $transport = $text === '' ? [] : json_decode($text, true, 32, JSON_THROW_ON_ERROR);
        }
        $caption = $this->ImportAssessmentText($message, $result['arguments'] ?? []) . (isset($result['summary']) ? "\n" . $result['summary'] : '');
        $notice = $this->EventImportBuffer('EventImportNotice');
        if (($notice['context'] ?? null) === $this->EventImportContext()) {
            $caption = $this->Translate('Device data could not be loaded:') . ' ' . $this->Translate(ExtensionContract::ErrorText($notice['error']));
        }
        if (isset($transport['workflow']['importEvent'])) {
            $caption = $this->Translate('Loading device data from Z2M. The selected event will be checked after the response.');
        }
        return ['ImportEvents' => ['values' => $report['rows'] ?? []],
            'ImportEventID'    => ['options' => array_merge([['caption' => $this->Translate('Choose event'), 'value' => 0]], $report['options'] ?? []), 'value' => $preview['event'] ?? $transport['workflow']['importEvent'] ?? 0,
                'enabled'                    => !isset($transport['workflow']['importEvent'])],
            'ImportToken'      => ['value' => $preview['token'] ?? ''],
            'ImportMessage'    => ['caption' => $caption],
            'ImportLoadButton' => ['enabled' => ($transport['workflow'] ?? null) === null],
            'ImportButton'     => ['enabled' => ($preview['token'] ?? '') !== '' && ($transport['workflow'] ?? null) === null]];
    }

    private function RefreshEventImportFields(?array $transport = null): void
    {
        foreach ($this->EventImportFields($transport) as $name => $changes) {
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
