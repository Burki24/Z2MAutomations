<?php

declare(strict_types=1);

namespace Z2MAutomations;

trait AutomationEditorAreas
{
    private const AREA_EDITOR_FIELDS = ['EditorDetails', 'EditorRule', 'EditorEditToken', 'EditorEditInfo', 'EditorName', 'EditorEnabled', 'EditorTriggerType', 'EditorDeviceTrigger', 'EditorSource', 'EditorSourceChoice', 'EditorHold', 'EditorTimeTrigger', 'EditorAt', 'EditorTimezone', 'ScheduleHelp', 'EditorConditions', 'EditorWindow', 'EditorAfter', 'EditorBefore', 'EditorWindowTimezone', 'EditorMonday', 'EditorTuesday', 'EditorWednesday', 'EditorThursday', 'EditorFriday', 'EditorSaturday', 'EditorSunday', 'WindowHelp', 'EditorConditionType', 'EditorCondition', 'EditorConditionChoice', 'EditorNumericProperty', 'EditorCompare', 'EditorNumber', 'ConditionHelp', 'EditorActionSection', 'EditorActions', 'EditorTarget', 'EditorTargetChoice', 'EditorDelay', 'EditorApply', 'CreateRuleButton', 'UpdateRuleButton', 'DeleteRuleButton'];

    private function ActiveEditorArea(): string
    {
        $draft = $this->ReadEditorDraft();
        $selection = $this->EditorSelection();
        $index = ($selection['checksum'] ?? null) === ($draft['draftChecksum'] ?? null)
            ? $this->EditorRuleIndex($draft, $selection['id'] ?? '') : null;
        if ($index !== null) {
            return $this->EditorRuleType($draft['configuration']['automations'][$index]);
        }
        $modes = $this->ReadAttributeString('EditorModes');
        $type = $modes === '' ? 'device' : (json_decode($modes, true, 8, JSON_THROW_ON_ERROR)['EditorTriggerType'] ?? 'device');
        return $type === 'time' ? 'time' : 'device';
    }

    private function CheckEditorArea(string $area): bool
    {
        if ($area === '' || (in_array($area, ['device', 'time'], true) && $this->ActiveEditorArea() === $area)) {
            return true;
        }
        $this->DebugEvent('FEHLER', 'Formularaktion aus einem nicht mehr aktiven Automationsbereich abgewiesen; aktuellen Bereich neu auswählen.', ['Bereich' => $this->EditorTypeLabel($area)]);
        return false;
    }

    private function UpdateEditorField(string $name, string $parameter, mixed $value): void
    {
        $this->UpdateFormField($name, $parameter, $value);
        if (!in_array($name, self::AREA_EDITOR_FIELDS, true)) {
            return;
        }
        $area = $this->ActiveEditorArea();
        if (($area === 'time' && $name === 'EditorDeviceTrigger') || ($area === 'device' && $name === 'EditorTimeTrigger')) {
            return;
        }
        if ($area !== 'advanced') {
            $this->UpdateFormField(($area === 'time' ? 'Time' : 'Device') . $name, $parameter, $value);
        }
    }

    private function FillAreaEditors(array &$form): void
    {
        $canonical = [];
        foreach ($this->EditorFormFields($form['actions']) as &$field) {
            if (in_array($field['name'] ?? '', self::AREA_EDITOR_FIELDS, true)) {
                $canonical[$field['name']] = $field;
            }
        }
        unset($field);
        $active = $this->ActiveEditorArea();
        foreach ($this->EditorFormFields($form['actions']) as &$field) {
            $name = $field['name'] ?? '';
            foreach (['device' => 'Device', 'time' => 'Time'] as $area => $stem) {
                if (!str_starts_with($name, $stem) || !isset($canonical[substr($name, strlen($stem))])) {
                    continue;
                }
                $original = substr($name, strlen($stem));
                $properties = ['options', 'columns'];
                if ($area === $active) {
                    $properties = array_merge($properties, ['value', 'values', 'visible', 'enabled', 'caption', 'expanded']);
                }
                $field = array_replace($field, array_intersect_key($canonical[$original], array_flip($properties)));
                if ($original === 'EditorTriggerType') {
                    $field['value'] = $area;
                    $field['visible'] = false;
                }
            }
        }
        unset($field);
    }
}
