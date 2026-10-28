<?php

declare(strict_types=1);

namespace Z2MAutomations;

use JsonException;

trait AutomationEditorActions
{
    public function InspectEditorActions(string $Steps, string $Change): bool
    {
        return $this->TraceModuleCall(__FUNCTION__, __LINE__, function () use ($Steps, $Change): bool
        {
            $reason = 'editor_actions';
            $actions = $this->BuildEditorActions($Steps, $reason);
            if (!in_array($Change, ['add', 'edit', 'delete', 'order'], true) || $actions === null) {
                $this->DebugEvent('FEHLER', 'Aktionsliste noch nicht gültig; vor der Übernahme korrigieren.', ['Grund' => $reason]);
                return false;
            }
            if ($Change === 'delete') {
                // Native onDelete supplies the list before the selected row is removed.
                $this->DebugEvent('AKTION', 'Löschen eines Aktionsschritts gemeldet; übergebene Anzahl ist der Vorbestand. Die verbleibende Liste wird beim Übernehmen geprüft.', ['AktionenVorLöschen' => count($actions)]);
                return true;
            }
            $this->DebugEvent('AKTION', 'Aktionsliste im Formular bearbeitet; noch nicht in den Regelentwurf übernommen.', ['Änderung' => $Change, 'Aktionen' => count($actions)]);
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
            $actions[] = ['type' => 'set', 'target' => ['type' => 'device', 'id' => $device],
                'values'         => [$choice['property'] => $choice['value']], 'delay_seconds' => $row['Delay']];
        }
        return $actions;
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
            if (($action['type'] ?? null) !== 'set' || ($action['target']['type'] ?? null) !== 'device'
                || count($action['values'] ?? []) !== 1 || !is_int($action['delay_seconds'] ?? 0)
                || ($action['delay_seconds'] ?? 0) < 0 || ($action['delay_seconds'] ?? 0) > 86400) {
                return null;
            }
            $device = $action['target']['id'];
            $property = array_key_first($action['values']);
            $found = null;
            foreach ($snapshot['devices'][$device]['actions'] ?? [] as $key => $choice) {
                if ($choice['property'] === $property && $choice['value'] === $action['values'][$property]) {
                    $found = $device . ':' . $key;
                    break;
                }
            }
            if ($found === null) {
                return null;
            }
            $rows[] = ['Choice' => $found, 'Delay' => $action['delay_seconds'] ?? 0];
        }
        return $rows;
    }

    private function EditorActionColumns(array $snapshot): array
    {
        $options = [['caption' => $this->Translate('Choose device and action'), 'value' => '']];
        foreach ($snapshot['devices'] ?? [] as $id => $device) {
            foreach ($this->EditorChoices($snapshot, $id, 'Target') as $choice) {
                if ($choice['value'] !== '') {
                    $options[] = ['caption' => $device['name'] . ' (' . $id . ') — ' . $choice['caption'], 'value' => $id . ':' . $choice['value']];
                }
            }
        }
        return [['name' => 'Choice', 'caption' => $this->Translate('Device and action'), 'width' => '70%', 'add' => '',
            'edit'      => ['type' => 'Select', 'options' => $options]],
            ['name'    => 'Delay', 'caption' => $this->Translate('Wait before this step (seconds)'), 'width' => '30%', 'add' => 0,
                'edit' => ['type' => 'NumberSpinner', 'minimum' => 0, 'maximum' => 86400]]];
    }

    private function FillActionSequenceForm(array &$form, array $selection, array $snapshot): void
    {
        foreach ($this->EditorFormFields($form['actions']) as &$field) {
            if (($field['name'] ?? '') === 'EditorActions') {
                $field['columns'] = $this->EditorActionColumns($snapshot);
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
