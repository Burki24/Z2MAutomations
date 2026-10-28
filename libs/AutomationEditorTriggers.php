<?php

declare(strict_types=1);

namespace Z2MAutomations;

use JsonException;

trait AutomationEditorTriggers
{
    public function InspectEditorTriggers(string $Triggers, string $Change, string $Area = ''): bool
    {
        return $this->TraceModuleCall(__FUNCTION__, __LINE__, function () use ($Triggers, $Change, $Area): bool
        {
            if (!$this->CheckEditorArea($Area)) {
                return false;
            }
            $triggers = $this->BuildEditorTriggers($Triggers);
            if (!in_array($Change, ['add', 'edit', 'delete'], true) || $triggers === null) {
                $this->DebugEvent('FEHLER', 'Auslöserliste noch nicht gültig; ein bis acht Geräteereignisse und eine Mindestdauer von 0 bis 86400 Sekunden wählen.');
                return false;
            }
            $comparisons = array_count_values(array_column($triggers, 'operator'));
            $details = $Change === 'delete' ? ['AuslöserVorLöschen' => count($triggers)] : ['Auslöser' => count($triggers)];
            $details['Vergleiche'] = $comparisons;
            $details['NurBeiWertänderung'] = count(array_filter($triggers, static fn (array $trigger): bool => $trigger['on_change'] ?? false));
            $this->DebugEvent('AKTION', $Change === 'delete'
                ? 'Auslöser zum Löschen ausgewählt; Anzahl beschreibt den Vorbestand. Verbleibende Liste wird beim Übernehmen geprüft.'
                : 'Auslöserliste im Formular bearbeitet; jedes Ereignis kann dieselbe Aktionsfolge starten (ODER).', $details);
            return true;
        });
    }

    private function EditorComparisonAllowed(array $snapshot, string $device, array $choice, mixed $operator): bool
    {
        $meta = $this->EditorProperties($snapshot, $device, 'Source')[$choice['property']] ?? null;
        return $meta !== null && is_string($operator) && in_array($operator, array_column($this->EditorComparisonOptions($meta['type'] === 'numeric' ? 'numeric' : 'state'), 'value'), true);
    }

    private function BuildEditorTriggers(string $text): ?array
    {
        if (strlen($text) > 4096) {
            return null;
        }
        try {
            $rows = json_decode($text, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
        if (!is_array($rows) || !array_is_list($rows) || count($rows) < 1 || count($rows) > 8) {
            return null;
        }
        $snapshot = $this->EditorSnapshot();
        $triggers = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !in_array(count($row), [3, 4], true)
                || array_diff(array_keys($row), ['Choice', 'Compare', 'Hold', 'Changed']) !== []
                || !is_string($row['Choice'] ?? null) || !is_int($row['Hold'] ?? null)
                || $row['Hold'] < 0 || $row['Hold'] > 86400 || !is_bool($row['Changed'] ?? false)) {
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
            $triggers[] = ['type' => 'device', 'device' => $device, 'property' => $choice['property'], 'operator' => $row['Compare'],
                'value'           => $choice['value'], 'for_seconds' => $row['Hold']];
            if ($row['Changed'] ?? false) {
                $triggers[array_key_last($triggers)]['on_change'] = true;
            }
        }
        return $triggers;
    }

    private function EditorTriggerRows(array $rule): ?array
    {
        if (count($rule['triggers'] ?? []) < 1 || count($rule['triggers']) > 8) {
            return null;
        }
        $snapshot = $this->EditorSnapshot();
        $rows = [];
        foreach ($rule['triggers'] as $trigger) {
            if (($trigger['type'] ?? '') !== 'device'
                || !is_int($trigger['for_seconds'] ?? 0) || ($trigger['for_seconds'] ?? 0) < 0 || ($trigger['for_seconds'] ?? 0) > 86400) {
                return null;
            }
            $found = null;
            foreach ($snapshot['devices'][$trigger['device']]['triggers'] ?? [] as $key => $choice) {
                if ($choice['property'] === $trigger['property'] && $choice['value'] === $trigger['value']) {
                    $found = $trigger['device'] . ':' . $key;
                    break;
                }
            }
            if ($found === null) {
                $key = $this->EditorValueKey($snapshot, $trigger['device'], $trigger['property'], $trigger['value'], 'Source');
                $found = $key === null ? null : $trigger['device'] . ':' . $key;
            }
            $choice = $found === null ? null : $this->FindEditorChoice($snapshot, $trigger['device'], explode(':', $found, 2)[1], 'Source');
            if ($choice === null || !$this->EditorComparisonAllowed($snapshot, $trigger['device'], $choice, $trigger['operator'] ?? null)) {
                return null;
            }
            $rows[] = ['Choice' => $found, 'Compare' => $trigger['operator'], 'Hold' => $trigger['for_seconds'] ?? 0]
                + (($trigger['on_change'] ?? false) ? ['Changed' => true] : []);
        }
        return $rows;
    }

    private function EditorTriggerColumns(array $snapshot, array $rows = []): array
    {
        $options = [['caption' => $this->Translate('Choose device and event'), 'value' => '']];
        foreach ($snapshot['devices'] ?? [] as $id => $device) {
            foreach ($this->EditorChoices($snapshot, $id, 'Source') as $choice) {
                if ($choice['value'] !== '') {
                    $options[] = ['caption' => $device['name'] . ' (' . $id . ') — ' . $choice['caption'], 'value' => $id . ':' . $choice['value']];
                }
            }
        }
        $options = $this->ExtraEditorChoiceOptions($snapshot, 'Source', $options, $rows);
        return [['name' => 'Choice', 'caption' => $this->Translate('Device and event'), 'width' => 'auto', 'add' => '', 'edit' => ['type' => 'Select', 'options' => $options]],
            ['name' => 'Compare', 'caption' => $this->Translate('Comparison'), 'width' => '140px', 'add' => 'eq', 'edit' => ['type' => 'Select', 'options' => $this->EditorComparisonOptions('numeric')]],
            ['name'    => 'Hold', 'caption' => $this->Translate('Minimum duration (s)'), 'width' => '140px', 'add' => 0,
                'edit' => ['type' => 'NumberSpinner', 'minimum' => 0, 'maximum' => 86400]],
            ['name'    => 'Changed', 'caption' => $this->Translate('Value change'), 'width' => '110px', 'add' => false,
                'edit' => ['type' => 'CheckBox']]];
    }

    private function FillTriggerSequenceForm(array &$form, array $selection, array $snapshot): void
    {
        foreach ($this->EditorFormFields($form['actions']) as &$field) {
            if (($field['name'] ?? '') === 'EditorTriggers') {
                $field['columns'] = $this->EditorTriggerColumns($snapshot, $selection['EditorTriggerRows']);
                $field['values'] = $selection['EditorTriggerRows'];
                $field['enabled'] = $selection['simple'];
            }
        }
        unset($field);
    }
}
