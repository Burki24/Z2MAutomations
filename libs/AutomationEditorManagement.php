<?php

declare(strict_types=1);

namespace Z2MAutomations;

trait AutomationEditorManagement
{
    public function SelectAutomation(string $RuleID, string $CurrentDraft = '', bool $CheckDraft = false, string $NewType = 'device'): bool
    {
        return $this->TraceModuleCall(__FUNCTION__, __LINE__, fn (): bool => $this->WithTransportLock(function () use ($RuleID, $CurrentDraft, $CheckDraft, $NewType): bool
        {
            $state = $this->RefreshState();
            $draft = $this->ReadEditorDraft();
            $error = $this->EditorContextError($state, $draft, $CurrentDraft, $CheckDraft, false);
            if ($error === '' && !in_array($NewType, ['device', 'time'], true)) {
                $error = 'editor_invalid';
            }
            $index = $this->EditorRuleIndex($draft, $RuleID);
            if ($error === '' && $RuleID !== '' && $index === null) {
                $error = 'editor_missing';
            }
            if ($error !== '') {
                return $this->RejectEditorChange($state, $error);
            }
            $this->SetEditorSelection($draft, $RuleID, $NewType);
            $this->UpdateEditorField('EditorDetails', 'expanded', true);
            $type = $index === null ? $NewType : $this->EditorRuleType($draft['configuration']['automations'][$index]);
            $this->DebugEvent('AKTION', 'Bearbeitung im passenden Automationsbereich geöffnet; Z2M bleibt unverändert.', ['Bereich' => $this->EditorTypeLabel($type), 'NeueAutomation' => $index === null]);
            $state['lastError'] = '';
            $state['operation'] = ['kind' => 'editor', 'status' => 'rule_selected', 'transaction' => ''];
            $this->DebugEvent('AKTION', 'Regelauswahl im lokalen Editor geladen.', ['VorhandeneRegel' => $index !== null, 'EmpfängerBestätigt' => $state['extensionConfirmed'], 'FelderVorbelegt' => $index !== null, 'BedingungenVorbelegt' => $index === null ? 0 : count($draft['configuration']['automations'][$index]['conditions'])]);
            $this->SaveState($state);
            return true;
        }));
    }

    public function UpdateAutomation(string $RuleID, string $SelectionToken, string $Name, bool $Enabled, string $Source, string $Trigger, string $Target, string $Action, string $CurrentDraft = '', bool $CheckDraft = false): bool
    {
        return $this->TraceModuleCall(__FUNCTION__, __LINE__, fn (): bool => $this->WithTransportLock(function () use ($RuleID, $SelectionToken, $Name, $Enabled, $Source, $Trigger, $Target, $Action, $CurrentDraft, $CheckDraft): bool
        {
            $state = $this->RefreshState();
            $draft = $this->ReadEditorDraft();
            $error = $this->EditorContextError($state, $draft, $CurrentDraft, $CheckDraft);
            $index = $this->EditorRuleIndex($draft, $RuleID);
            if ($error === '' && !$this->MatchesEditorSelection($draft, $RuleID, $SelectionToken)) {
                $error = 'editor_changed';
            }
            if ($error === '' && $index === null) {
                $error = 'editor_missing';
            }
            $name = trim($Name);
            if ($error === '' && !$this->ValidEditorName($name)) {
                $error = 'editor_invalid';
            }
            if ($error !== '') {
                return $this->RejectEditorChange($state, $error);
            }
            $rule = $draft['configuration']['automations'][$index];
            if ($this->SimpleEditorFields($rule) !== null) {
                $snapshot = $this->EditorSnapshot();
                $source = $this->FindEditorChoice($snapshot, $Source, $Trigger, 'Source');
                $target = $this->FindEditorChoice($snapshot, $Target, $Action, 'Target');
                if ($source === null || $target === null) {
                    return $this->RejectEditorChange($state, 'editor_invalid');
                }
                $rule['triggers'][0] = array_replace($rule['triggers'][0], ['device' => $Source, 'property' => $source['property'], 'value' => $source['value']]);
                $rule['actions'][0] = $this->BuildEditorAction($Target, $target);
            } elseif ($Source !== '' || $Trigger !== '' || $Target !== '' || $Action !== '') {
                return $this->RejectEditorChange($state, 'editor_advanced');
            }
            $rule['name'] = $name;
            $rule['enabled'] = $Enabled;
            $draft['configuration']['automations'][$index] = $rule;
            return $this->CommitEditorChange($state, $draft, 'draft_updated', $RuleID);
        }));
    }

    public function DeleteAutomation(string $RuleID, string $SelectionToken, string $CurrentDraft = '', bool $CheckDraft = false, string $Area = ''): bool
    {
        return $this->TraceModuleCall(__FUNCTION__, __LINE__, fn (): bool => $this->WithTransportLock(function () use ($RuleID, $SelectionToken, $CurrentDraft, $CheckDraft, $Area): bool
        {
            if (!$this->CheckEditorArea($Area)) {
                return false;
            }
            $state = $this->RefreshState();
            $draft = $this->ReadEditorDraft();
            $error = $this->EditorContextError($state, $draft, $CurrentDraft, $CheckDraft);
            $index = $this->EditorRuleIndex($draft, $RuleID);
            if ($error === '' && !$this->MatchesEditorSelection($draft, $RuleID, $SelectionToken)) {
                $error = 'editor_changed';
            }
            if ($error === '' && $index === null) {
                $error = 'editor_missing';
            }
            if ($error !== '') {
                return $this->RejectEditorChange($state, $error);
            }
            array_splice($draft['configuration']['automations'], $index, 1);
            return $this->CommitEditorChange($state, $draft, 'draft_deleted', '');
        }));
    }

    private function ReadEditorDraft(): ?array
    {
        $text = $this->ReadAttributeString('EditorDraft');
        return $text === '' ? null : json_decode($text, true, 32, JSON_THROW_ON_ERROR);
    }

    private function EditorContextError(array $state, ?array $draft, string $currentDraft, bool $checkDraft, bool $requireReceiver = true): string
    {
        if ($draft === null || $this->EditorSnapshot() === []) {
            return 'editor_unavailable';
        }
        $receiverChanged = $state['extensionConfirmed'] && ($draft['session'] !== $state['extension']['session']
            || $draft['baseRevision'] !== $state['extension']['storedRevision'] || $draft['baseChecksum'] !== $state['extension']['checksum']);
        return ($requireReceiver && !$state['extensionConfirmed']) || $receiverChanged || $state['workflow'] !== null || $draft['scope'] !== $state['scope']
            || $draft['draftChecksum'] !== hash('sha256', $this->ReadAttributeString('YamlDraft'))
            || (($checkDraft || $currentDraft !== '') && hash('sha256', $currentDraft) !== $draft['draftChecksum']) ? 'editor_changed' : '';
    }

    private function EditorRuleIndex(?array $draft, string $id): ?int
    {
        foreach ($draft['configuration']['automations'] ?? [] as $index => $rule) {
            if (($rule['id'] ?? null) === $id) {
                return $index;
            }
        }
        return null;
    }

    private function ValidEditorName(string $name): bool
    {
        return $name !== '' && strlen($name) <= 480 && preg_match('//u', $name) === 1
            && preg_match_all('/./us', $name) <= 120 && !preg_match('/[\x00-\x1F\x7F]/', $name);
    }

    private function EditorSelection(): array
    {
        $text = $this->ReadAttributeString('EditorSelection');
        return $text === '' ? [] : json_decode($text, true, 32, JSON_THROW_ON_ERROR);
    }

    private function MatchesEditorSelection(?array $draft, string $id, string $token): bool
    {
        $selection = $this->EditorSelection();
        return $id !== '' && $token !== '' && ($selection['id'] ?? null) === $id
            && ($selection['token'] ?? null) === $token && ($selection['checksum'] ?? null) === ($draft['draftChecksum'] ?? null);
    }

    private function SimpleEditorFields(array $rule): ?array
    {
        if (count($rule['triggers'] ?? []) !== 1 || ($rule['conditions'] ?? []) !== [] || count($rule['actions'] ?? []) !== 1) {
            return null;
        }
        $trigger = $rule['triggers'][0];
        $action = $rule['actions'][0];
        if (($trigger['type'] ?? null) !== 'device' || ($trigger['operator'] ?? null) !== 'eq' || ($trigger['for_seconds'] ?? 0) !== 0
            || ($action['type'] ?? null) !== 'set' || !in_array($action['target']['type'] ?? '', ['device', 'group'], true) || ($action['delay_seconds'] ?? 0) !== 0
            || count($action['values'] ?? []) !== 1) {
            return null;
        }
        $snapshot = $this->EditorSnapshot();
        $find = static function (array $choices, string $property, mixed $value): string
        {
            foreach ($choices as $key => $choice) {
                if ($choice['property'] === $property && $choice['value'] === $value) {
                    return $key;
                }
            }
            return '';
        };
        $property = array_key_first($action['values']);
        $sourceChoice = $find($snapshot['devices'][$trigger['device']]['triggers'] ?? [], $trigger['property'], $trigger['value']);
        $targetChoice = $find($snapshot['devices'][$this->EditorTargetKey($action['target'])]['actions'] ?? [], $property, $action['values'][$property]);
        return $sourceChoice === '' || $targetChoice === '' ? null : ['EditorSource' => $trigger['device'], 'EditorSourceChoice' => $sourceChoice,
            'EditorTarget'                                                           => $this->EditorTargetKey($action['target']), 'EditorTargetChoice' => $targetChoice];
    }

    private function EditorSelectionFields(?array $draft): array
    {
        $selection = $this->EditorSelection();
        $id = $selection['id'] ?? '';
        $index = $this->EditorRuleIndex($draft, $id);
        $selected = $index !== null && ($selection['checksum'] ?? null) === ($draft['draftChecksum'] ?? null);
        $rule = $selected ? $draft['configuration']['automations'][$index] : null;
        $simple = $rule === null ? null : $this->ScheduledEditorFields($rule);
        return ['EditorRule'      => $selected ? $id : '', 'EditorEditToken' => $selected ? $selection['token'] : '',
            'EditorName'          => $rule['name'] ?? '', 'EditorEnabled' => $rule['enabled'] ?? true,
            'EditorSource'        => $simple['EditorSource'] ?? '', 'EditorSourceChoice' => $simple['EditorSourceChoice'] ?? '',
            'EditorTarget'        => $simple['EditorTarget'] ?? '', 'EditorTargetChoice' => $simple['EditorTargetChoice'] ?? '',
            'EditorActionRows'    => $simple === null ? [] : $this->EditorActionRows($rule),
            'EditorConditionRows' => $simple === null ? [] : $this->EditorConditionRows($rule),
            'EditorTriggerRows'   => $simple === null || ($rule['triggers'][0]['type'] ?? '') !== 'device' ? [] : $this->EditorTriggerRows($rule),
            'selected'            => $selected, 'simple' => $simple !== null || !$selected] + array_intersect_key($simple ?? $this->ScheduleDefaults(), $this->ScheduleDefaults());
    }

    private function SetEditorSelection(array $draft, string $id, string $newType = ''): void
    {
        $previousArea = $this->ActiveEditorArea();
        $this->WriteAttributeString('EditorSelection', $id === '' ? '' : json_encode(['id' => $id, 'checksum' => $draft['draftChecksum'], 'token' => bin2hex(random_bytes(16))], JSON_THROW_ON_ERROR));
        $fields = $this->EditorSelectionFields($draft);
        if (!$fields['selected']) {
            $fields['EditorTriggerType'] = $newType !== '' ? $newType : ($previousArea === 'time' ? 'time' : 'device');
        } else {
            $index = $this->EditorRuleIndex($draft, $id);
            $type = $this->EditorRuleType($draft['configuration']['automations'][$index]);
            $fields['EditorTriggerType'] = $type === 'time' ? 'time' : $fields['EditorTriggerType'];
        }
        $this->ApplyScheduleFields($fields);
        $snapshot = $this->EditorSnapshot();
        foreach (['Source', 'Target'] as $side) {
            $this->WriteAttributeString('Editor' . $side, $fields['Editor' . $side]);
            $this->UpdateEditorField('Editor' . $side . 'Choice', 'options', json_encode($this->EditorChoices($snapshot, $fields['Editor' . $side], $side), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        }
        $this->UpdateEditorField('EditorActions', 'columns', json_encode($this->EditorActionColumns($snapshot, $fields['EditorActionRows']), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $this->UpdateEditorField('EditorActions', 'values', json_encode($fields['EditorActionRows'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $this->UpdateEditorField('EditorActions', 'enabled', $fields['simple']);
        $this->UpdateEditorField('EditorTriggers', 'columns', json_encode($this->EditorTriggerColumns($snapshot, $fields['EditorTriggerRows']), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $this->UpdateEditorField('EditorTriggers', 'values', json_encode($fields['EditorTriggerRows'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $this->UpdateEditorField('EditorTriggers', 'enabled', $fields['simple']);
        $this->UpdateEditorField('EditorConditionList', 'columns', json_encode($this->EditorConditionColumns($snapshot, $fields['EditorConditionRows']), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $this->UpdateEditorField('EditorConditionList', 'values', json_encode($fields['EditorConditionRows'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $this->UpdateEditorField('EditorConditionList', 'enabled', $fields['simple']);
        foreach ($fields as $field => $value) {
            if (!in_array($field, ['selected', 'simple', 'EditorActionRows', 'EditorTriggerRows', 'EditorConditionRows'], true)) {
                $this->UpdateEditorField($field, 'value', $value);
            }
        }
        $this->UpdateEditorField('CreateRuleButton', 'visible', !$fields['selected']);
        $this->UpdateEditorField('UpdateRuleButton', 'visible', $fields['selected']);
        $this->UpdateEditorField('DeleteRuleButton', 'enabled', $fields['selected']);
        foreach (['EditorSource', 'EditorSourceChoice', 'EditorTarget', 'EditorTargetChoice'] as $field) {
            $this->UpdateEditorField($field, 'enabled', $fields['simple']);
        }
        $this->UpdateEditorField('EditorEditInfo', 'caption', $this->EditorEditInfo($fields));
        $this->RefreshAutomationSections($draft);
        $this->UpdateEditorField('EditorConditions', 'expanded', $fields['EditorWindow'] || $fields['EditorConditionType'] !== 'none');
    }

    private function EditorEditInfo(array $fields): string
    {
        return $this->Translate($fields['simple'] ? 'Changes affect the local draft. Validate and store it afterwards.'
            : 'Advanced rule: name and enabled state can be changed. Conditions and actions are preserved.');
    }

    private function EditorRuleOptions(array $draft, string $type = ''): array
    {
        $options = [['caption' => $this->Translate('Create a new automation'), 'value' => '']];
        foreach ($draft['configuration']['automations'] as $index => $rule) {
            if ($type !== '' && $this->EditorRuleType($rule) !== $type) {
                continue;
            }
            $options[] = ['caption' => $rule['name'] . ' (' . ($index + 1) . ')', 'value' => $rule['id']];
        }
        return $options;
    }

    private function EditorRuleType(array $rule): string
    {
        $type = $rule['triggers'][0]['type'] ?? '';
        if (($rule['triggers'] ?? []) !== [] && count(array_filter($rule['triggers'], static fn (array $t): bool => ($t['type'] ?? '') === 'device')) === count($rule['triggers'])) {
            return 'device';
        }
        return count($rule['triggers'] ?? []) === 1 && $type === 'time' ? 'time' : 'advanced';
    }

    private function EditorTypeLabel(string $type): string
    {
        return match ($type) {
            'device' => 'Geräteausgelöst', 'time' => 'Zeitgesteuert', default => 'Weitere Auslöser'
        };
    }

    private function AutomationSectionFields(?array $draft, array $selection): array
    {
        $draft ??= ['configuration' => ['automations' => []]];
        $selectedIndex = $this->EditorRuleIndex($draft, $selection['EditorRule']);
        $selectedType = $selectedIndex === null ? '' : $this->EditorRuleType($draft['configuration']['automations'][$selectedIndex]);
        $fields = [];
        foreach (['device' => 'Device', 'time' => 'Time', 'advanced' => 'Advanced'] as $type => $stem) {
            $rows = $this->EditorRuleRows($draft, $type);
            $fields['Editor' . $stem . 'Rules'] = ['values' => $rows];
            $fields['Editor' . $stem . 'Rule'] = ['options' => $this->EditorRuleOptions($draft, $type), 'value' => $selectedType === $type ? $selection['EditorRule'] : ''];
            if ($type === 'advanced') {
                $fields['EditorAdvancedSection'] = ['visible' => $rows !== []];
            }
        }
        $active = $this->ActiveEditorArea();
        foreach (['device' => 'Device', 'time' => 'Time'] as $type => $stem) {
            $fields[$stem . 'EditorDetails'] = ['visible' => true, 'expanded' => $active === $type];
        }
        $fields['EditorDetails'] = ['visible' => $active === 'advanced', 'expanded' => true];
        return $fields;
    }

    private function FillAutomationSections(array &$form, ?array $draft, array $selection): void
    {
        $updates = $this->AutomationSectionFields($draft, $selection);
        $this->FillAreaEditors($form);
        $conditionPanel = ($this->ActiveEditorArea() === 'advanced' ? '' : ($this->ActiveEditorArea() === 'time' ? 'Time' : 'Device')) . 'EditorConditions';
        $updates[$conditionPanel] = ['expanded' => $selection['EditorWindow'] || $selection['EditorConditionType'] !== 'none'];
        $updates['EditorConditions'] = $updates[$conditionPanel];
        foreach ($this->EditorFormFields($form['actions']) as &$field) {
            $field = array_replace($field, $updates[$field['name'] ?? ''] ?? []);
        }
        unset($field);
    }

    private function RefreshAutomationSections(array $draft): void
    {
        foreach ($this->AutomationSectionFields($draft, $this->EditorSelectionFields($draft)) as $name => $changes) {
            foreach ($changes as $parameter => $value) {
                $this->UpdateEditorField($name, $parameter, is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : $value);
            }
        }
    }

    private function RejectEditorChange(array $state, string $error): bool
    {
        $state['lastError'] = $error;
        $this->DebugEvent('FEHLER', 'Regeländerung im lokalen Entwurf abgelehnt.', ['Grund' => $error, 'Hinweis' => $this->Translate(ExtensionContract::ErrorText($error))]);
        $this->SaveState($state);
        return false;
    }

    private function CommitEditorChange(array $state, array $draft, string $status, string $selection): bool
    {
        $draft['configuration']['revision'] = $draft['baseRevision'] + 1;
        $yaml = json_encode($draft['configuration'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        if (!ExtensionContract::ValidYamlText($yaml)) {
            return $this->RejectEditorChange($state, 'editor_invalid');
        }
        $this->SaveEditorDraft($draft, $yaml);
        $this->SetEditorSelection($this->ReadEditorDraft(), $selection);
        $state['validation'] = null;
        $state['lastError'] = '';
        $state['operation'] = ['kind' => 'editor', 'status' => $status, 'transaction' => ''];
        $this->DebugEvent('AKTION', 'Lokaler Regelentwurf geändert; Z2M noch unverändert.', ['Bereich' => $this->EditorTypeLabel($this->ActiveEditorArea()), 'Änderung' => $status, 'Automationen' => count($draft['configuration']['automations'])]);
        $this->SaveState($state);
        return true;
    }
}
