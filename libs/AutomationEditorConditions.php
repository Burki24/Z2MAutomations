<?php

declare(strict_types=1);

namespace Z2MAutomations;

use JsonException;

trait AutomationEditorConditions
{
    public function InspectEditorConditions(string $Conditions, string $Change, string $Area = ''): bool
    {
        return $this->TraceModuleCall(__FUNCTION__, __LINE__, function () use ($Conditions, $Change, $Area): bool
        {
            if (!$this->CheckEditorArea($Area)) {
                return false;
            }
            $conditions = $this->BuildEditorConditions($Conditions);
            if (!in_array($Change, ['add', 'edit', 'delete'], true) || $conditions === null) {
                $this->DebugEvent('FEHLER', 'Bedingungsliste noch nicht gültig; lesbare Eigenschaften und passende Vergleiche wählen.');
                return false;
            }
            $this->DebugEvent(
                'AKTION',
                $Change === 'delete'
                ? 'Bedingung zum Löschen ausgewählt; Anzahl beschreibt den Vorbestand. Verbleibende Liste wird beim Übernehmen geprüft.'
                : 'Bedingungsliste im Formular bearbeitet; alle Bedingungen müssen gemeinsam erfüllt sein (UND).',
                [$Change === 'delete' ? 'BedingungenVorLöschen' : 'Bedingungen' => count($conditions)]
            );
            return true;
        });
    }

    private function BuildEditorConditions(string $text): ?array
    {
        if (strlen($text) > 16384) {
            return null;
        }
        try {
            $rows = json_decode($text, false, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
        if (!is_array($rows) || !array_is_list($rows) || count($rows) > 16) {
            return null;
        }
        $snapshot = $this->EditorSnapshot();
        $conditions = [];
        foreach ($rows as $row) {
            if (!$row instanceof \stdClass) {
                return null;
            }
            $row = (array) $row;
            if (count($row) !== 2 || !is_string($row['Choice'] ?? null)) {
                return null;
            }
            $parts = explode(':', $row['Choice']);
            if (count($parts) !== 2) {
                return null;
            }
            [$device, $key] = $parts;
            $choice = $this->FindEditorChoice($snapshot, $device, $key, 'Source');
            if ($choice === null || !$this->EditorComparisonAllowed($snapshot, $device, $choice, $row['Compare'] ?? null)) {
                return null;
            }
            $conditions[] = ['type' => 'device_value', 'device' => $device, 'property' => $choice['property'], 'operator' => $row['Compare'], 'value' => $choice['value']];
        }
        return $conditions;
    }

    private function EditorConditionRows(array $rule): ?array
    {
        $snapshot = $this->EditorSnapshot();
        $rows = [];
        foreach ($rule['conditions'] ?? [] as $condition) {
            if (($condition['type'] ?? '') === 'time_window') {
                continue;
            }
            if (($condition['type'] ?? '') !== 'device_value') {
                return null;
            }
            $key = $this->EditorValueKey($snapshot, $condition['device'], $condition['property'], $condition['value'], 'Source');
            $choice = $key === null ? null : $this->FindEditorChoice($snapshot, $condition['device'], $key, 'Source');
            if ($choice === null || !$this->EditorComparisonAllowed($snapshot, $condition['device'], $choice, $condition['operator'])) {
                return null;
            }
            $rows[] = ['Choice' => $condition['device'] . ':' . $key, 'Compare' => $condition['operator']];
        }
        return count($rows) <= 16 ? $rows : null;
    }

    private function EditorConditionColumns(array $snapshot, array $rows = []): array
    {
        $columns = $this->EditorTriggerColumns($snapshot, $rows);
        $columns[0]['caption'] = $this->Translate('Device, property and value');
        return array_slice($columns, 0, 2);
    }

    private function FillConditionSequenceForm(array &$form, array $selection, array $snapshot): void
    {
        foreach ($this->EditorFormFields($form['actions']) as &$field) {
            if (($field['name'] ?? '') === 'EditorConditionList') {
                $field['columns'] = $this->EditorConditionColumns($snapshot, $selection['EditorConditionRows']);
                $field['values'] = $selection['EditorConditionRows'];
                $field['enabled'] = $selection['simple'];
            }
        }
        unset($field);
    }
}
