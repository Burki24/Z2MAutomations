<?php

declare(strict_types=1);

namespace Z2MAutomations;

use JsonException;

trait AutomationEditorActions
{
    public function InspectEditorActions(string $Steps, string $Change, string $Area = ''): bool
    {
        return $this->TraceModuleCall(__FUNCTION__, __LINE__, function () use ($Steps, $Change, $Area): bool
        {
            if (!$this->CheckEditorArea($Area)) {
                return false;
            }
            $reason = 'editor_actions';
            $actions = $this->BuildEditorActions($Steps, $reason);
            if (!in_array($Change, ['add', 'edit', 'delete', 'order'], true) || $actions === null) {
                $this->DebugEvent('FEHLER', 'Aktionsliste noch nicht gültig; vor der Übernahme korrigieren.', ['Grund' => $reason]);
                return false;
            }
            if ($Change === 'delete') {
                // Native onDelete supplies the list before the selected row is removed.
                $this->DebugEvent('AKTION', 'Löschen eines Aktionsschritts gemeldet; übergebene Anzahl ist der Vorbestand. Die verbleibende Liste wird beim Übernehmen geprüft.', ['Bereich' => $this->EditorTypeLabel($this->ActiveEditorArea()), 'AktionenVorLöschen' => count($actions)]);
                return true;
            }
            $this->DebugEvent('AKTION', 'Aktionsliste im Formular bearbeitet; noch nicht in den Regelentwurf übernommen.', ['Bereich' => $this->EditorTypeLabel($this->ActiveEditorArea()), 'Änderung' => $Change, 'Aktionen' => count($actions)]);
            $this->DebugEditorActionSteps($actions);
            return true;
        });
    }

    private function BuildEditorActions(string $steps, string &$reason): ?array
    {
        if (strlen($steps) > 8192) {
            return null;
        }
        try {
            $rows = json_decode($steps, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
        if (!is_array($rows) || !array_is_list($rows) || count($rows) < 1 || count($rows) > 16) {
            return null;
        }
        $snapshot = $this->EditorSnapshot();
        $actions = [];
        foreach ($rows as $row) {
            if (!is_array($row) || count($row) !== 2 || !is_string($row['Choice'] ?? null) || !is_int($row['Delay'] ?? null)) {
                return null;
            }
            if ($row['Delay'] < 0 || $row['Delay'] > 86400) {
                $reason = 'editor_duration';
                return null;
            }
            $parts = explode(':', $row['Choice']);
            if (count($parts) !== 2) {
                return null;
            }
            [$device, $key] = $parts;
            $choice = $this->FindEditorChoice($snapshot, $device, $key, 'Target');
            if ($choice === null) {
                return null;
            }
            $actions[] = $this->BuildEditorAction($device, $choice, $row['Delay']);
        }
        return $actions;
    }

    private function BuildEditorAction(string $key, array $choice, int $delay = 0): array
    {
        $target = $this->EditorTargetIdentity($key);
        $meta = $this->EditorProperties($this->EditorSnapshot(), $key, 'Target')[$choice['property']] ?? [];
        return $choice['value'] === 'TOGGLE' && ($meta['type'] ?? '') === 'binary'
            && preg_match('/\Astate(?:_[A-Za-z0-9_]+)?\z/D', $choice['property']) === 1
            ? ['type' => 'toggle', 'target' => $target, 'property' => $choice['property'], 'delay_seconds' => $delay]
            : ['type' => 'set', 'target' => $target, 'values' => [$choice['property'] => $choice['value']], 'delay_seconds' => $delay];
    }

    private function EditorActionValues(array $action): array
    {
        return ($action['type'] ?? '') === 'toggle' ? [$action['property'] => 'TOGGLE'] : ($action['values'] ?? []);
    }

    private function EditorActionRows(array $rule): ?array
    {
        $actions = $rule['actions'] ?? [];
        if (count($actions) < 1 || count($actions) > 16) {
            return null;
        }
        $snapshot = $this->EditorSnapshot();
        $rows = [];
        foreach ($actions as $action) {
            $values = $this->EditorActionValues($action);
            if (!in_array($action['type'] ?? '', ['set', 'toggle'], true) || !in_array($action['target']['type'] ?? '', ['device', 'group'], true)
                || count($values) !== 1 || !is_int($action['delay_seconds'] ?? 0)
                || ($action['delay_seconds'] ?? 0) < 0 || ($action['delay_seconds'] ?? 0) > 86400) {
                return null;
            }
            $device = $this->EditorTargetKey($action['target']);
            $property = array_key_first($values);
            if ($action['type'] === 'set' && ($values[$property] ?? null) === 'TOGGLE'
                && $this->BuildEditorAction($device, ['property' => $property, 'value' => 'TOGGLE'])['type'] === 'toggle') {
                // Preserve an explicitly authored native TOGGLE as an advanced rule;
                // editing must not change it into aggregate-state inversion.
                return null;
            }
            $found = null;
            foreach ($snapshot['devices'][$device]['actions'] ?? [] as $key => $choice) {
                if ($choice['property'] === $property && $choice['value'] === $values[$property]) {
                    $found = $device . ':' . $key;
                    break;
                }
            }
            if ($found === null) {
                $key = $this->EditorValueKey($snapshot, $device, $property, $values[$property], 'Target');
                $found = $key === null ? null : $device . ':' . $key;
            }
            if ($found === null) {
                return null;
            }
            $rows[] = ['Choice' => $found, 'Delay' => $action['delay_seconds'] ?? 0];
        }
        return $rows;
    }

    private function EditorActionColumns(array $snapshot, array $rows = []): array
    {
        $options = [['caption' => $this->Translate('Choose device and action'), 'value' => '']];
        foreach ($snapshot['devices'] ?? [] as $id => $device) {
            foreach ($this->EditorChoices($snapshot, $id, 'Target') as $choice) {
                if ($choice['value'] !== '') {
                    $options[] = ['caption' => $this->EditorDeviceLabel($device, $id) . ' — ' . $choice['caption'], 'value' => $id . ':' . $choice['value']];
                }
            }
        }
        $options = $this->ExtraEditorChoiceOptions($snapshot, 'Target', $options, $rows);
        return [['name' => 'Choice', 'caption' => $this->Translate('Device and action'), 'width' => 'auto', 'add' => '',
            'edit'      => ['type' => 'Select', 'options' => $options]],
            ['name'    => 'Delay', 'caption' => $this->Translate('Delay (s)'), 'width' => '140px', 'add' => 0,
                'edit' => ['type' => 'NumberSpinner', 'minimum' => 0, 'maximum' => 86400]]];
    }

    private function FillActionSequenceForm(array &$form, array $selection, array $snapshot): void
    {
        foreach ($this->EditorFormFields($form['actions']) as &$field) {
            if (($field['name'] ?? '') === 'EditorActions') {
                $field['columns'] = $this->EditorActionColumns($snapshot, $selection['EditorActionRows']);
                $field['values'] = $selection['EditorActionRows'];
                $field['enabled'] = $selection['simple'];
            }
        }
        unset($field);
    }

    private function DebugEditorActionSteps(array $actions): void
    {
        foreach ($actions as $index => $action) {
            $this->DebugEvent('AKTION', 'Aktionsschritt mit Wartezeit vor der Ausführung vorbereitet.', ['Schritt' => $index + 1, 'VerzögerungSekunden' => $action['delay_seconds'] ?? 0]);
        }
    }
}
