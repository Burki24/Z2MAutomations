<?php

declare(strict_types=1);

namespace Z2MAutomations;

trait EditorItemForms
{
    public function GetEditorItemForm(string $List, string $Choice, int $Duration): string
    {
        return $this->TraceModuleCall(__FUNCTION__, __LINE__, function () use ($List, $Choice, $Duration): string
        {
            $context = $this->EditorItemContext($List);
            if ($context === null || !$this->CheckEditorArea($context['area'])) {
                return '[]';
            }
            $form = $this->LoadConfigurationForm();
            $template = [];
            foreach ($this->EditorFormFields($form['actions']) as &$field) {
                if (($field['name'] ?? '') === $List) {
                    $template = $field['form'];
                    break;
                }
            }
            unset($field);
            $snapshot = $this->EditorSnapshot();
            $parts = explode(':', $Choice);
            $device = count($parts) === 2 ? $parts[0] : '';
            $choice = count($parts) === 2 ? $this->FindEditorChoice($snapshot, $device, $parts[1], $context['side']) : null;
            $binding = $choice !== null && $context['side'] === 'Target' ? $this->EditorActionVariable($snapshot, $device, $choice['property']) : null;
            $property = $choice === null ? '' : ($binding === null ? $choice['property'] : (string) $binding['id']);
            $meta = $this->EditorItemProperty($snapshot, $device, $property, $context['side']);
            $numeric = ($meta['type'] ?? '') === 'numeric';
            foreach ($template as &$field) {
                $name = $field['name'] ?? '';
                if ($name === 'RowDevice') {
                    $field['options'] = $this->EditorDevices($snapshot, $context['side']);
                    $field['value'] = $device;
                } elseif ($name === 'RowProperty') {
                    $field['options'] = $this->EditorItemProperties($snapshot, $device, $context['side']);
                    $field['value'] = $property;
                } elseif ($name === 'RowValue') {
                    $field['options'] = $this->EditorItemValues($snapshot, $device, $meta, $context['side']);
                    $field['value'] = !$numeric && $choice !== null ? $parts[1] : '';
                    $field['visible'] = !$numeric;
                } elseif ($name === 'RowNumber') {
                    $field['value'] = $numeric ? $choice['value'] : 0;
                    $field['visible'] = $numeric;
                    $field += array_intersect_key($meta ?? [], array_flip(['minimum', 'maximum']));
                } elseif ($name === 'Choice') {
                    $field['value'] = $choice === null ? '' : $Choice;
                } elseif ($name === $context['duration']) {
                    $field['value'] = $Duration;
                }
            }
            unset($field);
            $this->DebugEvent('AKTION', 'Zeilenbearbeitung mit getrennter Geräte-, Eigenschafts- und Wertauswahl geöffnet.', ['Auswahlart' => $context['side'], 'Vorbelegt' => $choice !== null]);
            return json_encode($template, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        });
    }

    public function ConfigureEditorItem(string $Change, string $List, string $Device, string $Property, string $Value): void
    {
        $this->TraceModuleCall(__FUNCTION__, __LINE__, function () use ($Change, $List, $Device, $Property, $Value): void
        {
            $context = $this->EditorItemContext($List);
            if ($context === null || !$this->CheckEditorArea($context['area']) || !in_array($Change, ['device', 'property', 'value'], true)) {
                $this->DebugEvent('FEHLER', 'Unbekannte Auswahl im Zeilendialog abgewiesen.');
                return;
            }
            $snapshot = $this->EditorSnapshot();
            if ($Change === 'device') {
                $Property = '';
                $this->UpdateFormField('RowProperty', 'options', json_encode($this->EditorItemProperties($snapshot, $Device, $context['side']), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
                $this->UpdateFormField('RowProperty', 'value', '');
            }
            $meta = $this->EditorItemProperty($snapshot, $Device, $Property, $context['side']);
            $numeric = ($meta['type'] ?? '') === 'numeric';
            $choice = null;
            if ($Change !== 'value') {
                $this->UpdateFormField('RowValue', 'options', json_encode($this->EditorItemValues($snapshot, $Device, $meta, $context['side']), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
                $this->UpdateFormField('RowValue', 'value', '');
                $number = max($meta['minimum'] ?? -PHP_FLOAT_MAX, min($meta['maximum'] ?? PHP_FLOAT_MAX, 0));
                $this->UpdateFormField('RowNumber', 'value', $number);
                // Reset bounds when switching between different numeric properties.
                $this->UpdateFormField('RowNumber', 'minimum', $meta['minimum'] ?? -PHP_FLOAT_MAX);
                $this->UpdateFormField('RowNumber', 'maximum', $meta['maximum'] ?? PHP_FLOAT_MAX);
                if ($numeric) {
                    $key = $this->EditorValueKey($snapshot, $Device, $meta['property'], $number, $context['side']);
                    $choice = $key === null ? null : $Device . ':' . $key;
                }
            } elseif ($meta !== null && strlen($Value) <= 4096) {
                $value = json_decode($Value, true, 8);
                if ($numeric) {
                    $key = $this->EditorValueKey($snapshot, $Device, $meta['property'], $value, $context['side']);
                } else {
                    $candidate = is_string($value) ? $this->FindEditorChoice($snapshot, $Device, $value, $context['side']) : null;
                    $key = ($candidate['property'] ?? '') === $meta['property'] ? $value : null;
                }
                $choice = $key === null ? null : $Device . ':' . $key;
            }
            $this->UpdateFormField('RowValue', 'visible', !$numeric);
            $this->UpdateFormField('RowNumber', 'visible', $numeric);
            $this->UpdateFormField('Choice', 'value', $choice ?? '');
            if ($choice !== null) {
                $saved = $this->GetBuffer('EditorRowChoices');
                $saved = $saved === '' ? [] : json_decode($saved, true, 16, JSON_THROW_ON_ERROR);
                $saved[] = $choice;
                $this->SetBuffer('EditorRowChoices', json_encode(array_slice(array_values(array_unique($saved)), -128), JSON_THROW_ON_ERROR));
                $columns = $context['side'] === 'Target' ? $this->EditorActionColumns($snapshot) : $this->EditorTriggerColumns($snapshot);
                $this->UpdateEditorField($List, 'columns', json_encode($columns, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            }
            $this->DebugEvent('AKTION', 'Abhängige Eigenschafts- und Wertauswahl aktualisiert; alte Auswahl verworfen.', ['Änderung' => $Change, 'Auswahlart' => $context['side'], 'EigenschaftVerfügbar' => $meta !== null, 'WertGültig' => $choice !== null]);
        });
    }
    private function EditorItemContext(string $list): ?array
    {
        if (!in_array($list, ['EditorActions', 'EditorTriggers', 'DeviceEditorActions', 'DeviceEditorTriggers', 'TimeEditorActions', 'TimeEditorTriggers'], true)) {
            return null;
        }
        return ['side' => str_ends_with($list, 'Triggers') ? 'Source' : 'Target',
            'duration' => str_ends_with($list, 'Triggers') ? 'Hold' : 'Delay',
            'area'     => str_starts_with($list, 'Device') ? 'device' : (str_starts_with($list, 'Time') ? 'time' : '')];
    }

    private function EditorItemProperties(array $snapshot, string $device, string $side): array
    {
        $options = [['caption' => $this->Translate('Choose property or channel'), 'value' => '']];
        foreach ($this->EditorProperties($snapshot, $device, $side) as $property => $meta) {
            $binding = $side === 'Target' ? $this->EditorActionVariable($snapshot, $device, $property) : null;
            $options[] = ['caption' => $this->EditorPropertyLabel($snapshot, $device, $property, $side),
                'value'             => $binding === null ? $property : (string) $binding['id']];
        }
        return $options;
    }

    private function EditorItemProperty(array $snapshot, string $device, string $token, string $side): ?array
    {
        foreach ($this->EditorProperties($snapshot, $device, $side) as $property => $meta) {
            $binding = $side === 'Target' ? $this->EditorActionVariable($snapshot, $device, $property) : null;
            if (($binding === null ? $property : (string) $binding['id']) === $token) {
                return $meta;
            }
        }
        return null;
    }

    private function EditorItemValues(array $snapshot, string $device, ?array $meta, string $side): array
    {
        $options = [['caption' => $this->Translate('Choose value'), 'value' => '']];
        foreach ($meta['values'] ?? [] as $value) {
            $key = $this->EditorValueKey($snapshot, $device, $meta['property'], $value, $side);
            if ($key !== null) {
                $options[] = ['caption' => $this->EditorValueLabel($value), 'value' => $key];
            }
        }
        return $options;
    }

    private function EditorValueLabel(mixed $value): string
    {
        return $this->Translate(match ($value) {
            'ON' => 'On', 'OFF' => 'Off', 'single' => 'Single press', 'double' => 'Double press', 'hold' => 'Hold', 'release' => 'Release',
            true => 'Yes', false => 'No', default => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
        });
    }

    private function ExtraEditorChoiceOptions(array $snapshot, string $side, array $options, array $rows): array
    {
        $saved = $this->GetBuffer('EditorRowChoices');
        $saved = $saved === '' ? [] : json_decode($saved, true, 16, JSON_THROW_ON_ERROR);
        foreach (array_unique(array_merge($saved, array_column($rows, 'Choice'))) as $encoded) {
            $parts = explode(':', $encoded);
            if (count($parts) !== 2 || in_array($encoded, array_column($options, 'value'), true)) {
                continue;
            }
            [$device, $key] = $parts;
            $choice = $this->FindEditorChoice($snapshot, $device, $key, $side);
            if ($choice !== null) {
                $options[] = ['caption'                                                                                                                      => $snapshot['devices'][$device]['name'] . ' (' . $device . ') — '
                    . $this->EditorPropertyLabel($snapshot, $device, $choice['property'], $side) . ': ' . $this->EditorValueLabel($choice['value']), 'value' => $encoded];
            }
        }
        return $options;
    }

    private function PrepareEditorItemForms(array &$form): void
    {
        foreach ($this->EditorFormFields($form['actions']) as &$field) {
            $name = $field['name'] ?? '';
            $context = $this->EditorItemContext($name);
            if ($context !== null) {
                $duration = $context['duration'];
                $field['form'] = "return json_decode(Z2MA_GetEditorItemForm(\$id, '" . $name . "', $" . $name . "['Choice'] ?? '', $" . $name . "['" . $duration . "'] ?? 0), true);";
            }
        }
        unset($field);
    }
}
