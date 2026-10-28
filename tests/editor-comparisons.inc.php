<?php

declare(strict_types=1);

// Included in the real PHP/JavaScript integration harness with a synthetic catalog.
$module->SelectAutomation('', '', false, 'device');
$module->ConfigureEditorItem('value', 'DeviceEditorTriggers', '0x0000000000000004', 'level', '16.5', 'gt');
$thresholdChoice = $module->formUpdates['Choice']['value'];
$check($thresholdChoice !== '', 'Source thresholds may lie between sensor reporting steps');
$thresholdRow = ['Choice' => $thresholdChoice, 'Compare' => 'gt', 'Hold' => 0];
$thresholdForm = formFields(json_decode($module->GetEditorItemForm('DeviceEditorTriggers', $thresholdChoice, 7, 'gt'), true, 512, JSON_THROW_ON_ERROR));

$runEditorScript($thresholdForm['Compare']['onChange'], $thresholdForm, ['Compare' => 'lt']);
$check($module->formUpdates['Choice']['value'] === $thresholdChoice, 'Numeric comparison callback preserves the threshold choice');
$check($thresholdForm['RowNumber']['value'] === 16.5 && $thresholdForm['Compare']['value'] === 'gt' && $thresholdForm['Hold']['value'] === 7
    && count($thresholdForm['Compare']['options']) === 6, 'Numeric trigger dialog restores threshold, operator and duration');
foreach (['eq', 'ne', 'gt', 'gte', 'lt', 'lte'] as $operator) {
    $check($module->InspectEditorTriggers(json_encode([array_replace($thresholdRow, ['Compare' => $operator])]), 'edit', 'device'), 'Every numeric comparison is accepted');
}
$module->ConfigureEditorItem('value', 'DeviceEditorTriggers', '0x0000000000000004', 'level', '105.5', 'lte');
$check($module->formUpdates['Choice']['value'] !== '', 'Source thresholds can also be outside reported sensor bounds');
$module->ConfigureEditorItem('value', 'DeviceEditorActions', '0x0000000000000004', (string) $levelVariable, '105.5');
$check($module->formUpdates['Choice']['value'] === '', 'The same out-of-range value remains forbidden for an action');
$module->ConfigureEditorItem('value', 'DeviceEditorConditionList', '0x0000000000000004', 'level', '19.5', 'lt');
$conditionThreshold = $module->formUpdates['Choice']['value'];
$module->SelectEditorDevice('Source', '0x0000000000000002');
$catalogFields = formFields(json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR)['actions']);
// The state value has a stable typed catalog key.
$offChoice = '0x0000000000000002:' . hash('sha256', json_encode(['property' => 'state', 'value' => 'OFF'], JSON_THROW_ON_ERROR));
$conditions = [['Choice' => $offChoice, 'Compare' => 'eq'], ['Choice' => $conditionThreshold, 'Compare' => 'lt']];
$conditionForm = formFields(json_decode($module->GetEditorItemForm('DeviceEditorConditionList', $conditionThreshold, 0, 'lt'), true, 512, JSON_THROW_ON_ERROR));
$check($conditionForm['RowNumber']['value'] === 19.5 && $conditionForm['Compare']['value'] === 'lt' && !isset($conditionForm['Hold']), 'Condition row dialog restores numeric comparison without a trigger duration');
$stateForm = formFields(json_decode($module->GetEditorItemForm('DeviceEditorConditionList', $offChoice, 0, 'ne'), true, 512, JSON_THROW_ON_ERROR));
$runEditorScript($stateForm['Compare']['onChange'], $stateForm, ['Compare' => 'ne']);
$check($module->formUpdates['Choice']['value'] === $offChoice, 'Discrete comparison callback preserves the state choice');
$check(count($stateForm['Compare']['options']) === 2 && $stateForm['Compare']['value'] === 'ne', 'Discrete conditions offer equality and inequality only');
$check(!$module->InspectEditorConditions(json_encode([['Choice' => $offChoice, 'Compare' => 'gt']]), 'add', 'device'), 'Ordered comparisons on state strings are rejected');
$check(!$module->InspectEditorTriggers(json_encode([['Choice' => $offChoice, 'Compare' => 'gt', 'Hold' => 0]]), 'add', 'device'), 'State triggers cannot use numeric comparisons');
foreach (['{}', '[null]', json_encode([['Choice' => $offChoice, 'Compare' => 'unknown']]), json_encode(array_fill(0, 17, $conditions[0]))] as $invalid) {
    $check(!$module->InspectEditorConditions($invalid, 'edit', 'device'), 'Malformed or oversized condition lists are rejected');
}
$check($module->InspectEditorConditions(json_encode(array_fill(0, 16, $conditions[0])), 'add', 'device'), 'Sixteen device conditions fit the schema boundary');
$check($module->InspectEditorConditions('[]', 'delete', 'device'), 'An empty condition list is valid');
$fields = formFields(json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR)['actions']);
$runEditorScript($fields['DeviceEditorConditionList']['onAdd'], $fields, ['DeviceEditorConditionList' => new ArrayIterator($conditions)]);
$actionRows = [['Choice' => '0x0000000000000002:' . $action, 'Delay' => 0]];
$runEditorScript($fields['DeviceCreateRuleButton']['onClick'], $fields, [
    'DeviceEditorName'          => 'Synthetic threshold and AND rule', 'DeviceEditorEnabled' => true,
    'DeviceEditorWindow'        => false, 'DeviceEditorConditionType' => 'none',
    'DeviceEditorTriggers'      => new ArrayIterator([$thresholdRow]), 'DeviceEditorActions' => new ArrayIterator($actionRows),
    'DeviceEditorConditionList' => new ArrayIterator($conditions)]);
$check($state()['operation']['status'] === 'draft_created', 'Actual form button serializes comparisons and the condition list');
$fields = formFields(json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR)['actions']);
$document = json_decode($fields['YamlInput']['value'], true, 512, JSON_THROW_ON_ERROR);
$comparisonRule = $document['automations'][array_key_last($document['automations'])];
$check($comparisonRule['triggers'][0]['operator'] === 'gt' && $comparisonRule['triggers'][0]['value'] === 16.5
    && count($comparisonRule['conditions']) === 2 && $comparisonRule['conditions'][1]['operator'] === 'lt'
    && $comparisonRule['conditions'][1]['value'] === 19.5, 'Generated YAML preserves all typed comparisons and conditions');
$check($module->ValidateAutomationDraft() !== '' && $module->StoreAutomationDraft() !== '', 'Thresholds and multiple conditions validate and persist in the real receiver');
$module->LoadAutomationEditor();
$check($module->SelectAutomation($comparisonRule['id']), 'Reopen the stored comparison rule');
$fields = formFields(json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR)['actions']);
$check($fields['DeviceEditorTriggers']['values'] === [$thresholdRow] && $fields['DeviceEditorConditionList']['values'] === $conditions
    && $fields['DeviceEditorConditionList']['enabled'] && !$fields['DeviceEditorNumber']['visible'], 'Stored comparison triggers and every condition are restored in the active editor');
$DeviceEditorConditionList = $conditions[1];
$dynamicForm = formFields(eval($fields['DeviceEditorConditionList']['form']));
$check($dynamicForm['Compare']['value'] === 'lt' && $dynamicForm['RowNumber']['value'] === 19.5, 'The real condition list form script forwards the saved comparison');
$token = $fields['EditorEditToken']['value'];
$check(!$module->SaveScheduledAutomation($comparisonRule['id'], $token, 'Refuse missing conditions', true, '', '', '', '', '{}', '', false, json_encode($actionRows), 'device', json_encode([$thresholdRow])), 'Incomplete saves cannot silently truncate multiple conditions');
$check(!$module->SaveScheduledAutomation($comparisonRule['id'], $token, 'Too many conditions', true, '', '', '', '', json_encode(['EditorTriggerType' => 'device', 'EditorWindow' => true]), '', false, json_encode($actionRows), 'device', json_encode([$thresholdRow]), json_encode(array_fill(0, 16, $conditions[0]))), 'The optional time window counts toward the schema limit of sixteen conditions');
$check($module->ActivateAutomations() !== '', 'Activate the generated comparison rule in the synthetic receiver');
$setMessages = static fn (array $messages): array => array_values(array_filter($messages, static fn (object $m): bool => str_ends_with($m->topic, '/set')));
$exchange(['control' => 'event', 'device' => '0x0000000000000002', 'payload' => ['state' => 'OFF']]);
$messages = $exchange(['control' => 'event', 'device' => '0x0000000000000004', 'payload' => ['level' => 25]]);
$check($setMessages($messages) === [], 'A false numeric condition blocks a true threshold trigger');
$exchange(['control' => 'event', 'device' => '0x0000000000000002', 'payload' => ['state' => 'ON']]);
$messages = $exchange(['control' => 'event', 'device' => '0x0000000000000004', 'payload' => ['level' => 18]]);
$check($setMessages($messages) === [], 'A false state condition blocks execution even when the numeric condition matches');
$exchange(['control' => 'event', 'device' => '0x0000000000000002', 'payload' => ['state' => 'OFF']]);
$messages = $exchange(['control' => 'event', 'device' => '0x0000000000000004', 'payload' => ['level' => 18]]);
$check(count($setMessages($messages)) === 1, 'A true numeric trigger executes once when every condition matches');
$check($module->StopAutomations() !== '', 'Stop the synthetic comparison rule after execution');
// Editing and deleting list rows updates the draft without flattening other rows.
$module->SelectAutomation($comparisonRule['id']);
$fields = formFields(json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR)['actions']);
$editedConditions = [$conditions[1], array_replace($conditions[0], ['Compare' => 'ne'])];
$runEditorScript($fields['DeviceEditorConditionList']['onEdit'], $fields, ['DeviceEditorConditionList' => new ArrayIterator($editedConditions)]);
$runEditorScript($fields['DeviceUpdateRuleButton']['onClick'], $fields, ['DeviceEditorConditionList' => new ArrayIterator($editedConditions)]);
$check($state()['operation']['status'] === 'draft_updated', 'Actual edit button saves both revised conditions');
$fields = formFields(json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR)['actions']);
$check($fields['DeviceEditorConditionList']['values'] === $editedConditions, 'Edited comparisons and condition order reopen intact');
$runEditorScript($fields['DeviceEditorConditionList']['onDelete'], $fields, ['DeviceEditorConditionList' => new ArrayIterator($editedConditions)]);
$runEditorScript($fields['DeviceUpdateRuleButton']['onClick'], $fields, ['DeviceEditorConditionList' => new ArrayIterator([])]);
$fields = formFields(json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR)['actions']);
$check($fields['DeviceEditorConditionList']['values'] === [], 'Deleting the final condition leaves an explicit empty list');
// The separate time editor uses the same list and combines it with the time window.
$module->SelectAutomation('', '', false, 'time');
$fields = formFields(json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR)['actions']);
$runEditorScript($fields['TimeCreateRuleButton']['onClick'], $fields, [
    'TimeEditorName'    => 'Synthetic timed AND rule', 'TimeEditorEnabled' => false,
    'TimeEditorWindow'  => true, 'TimeEditorAfter' => '20:00:00', 'TimeEditorBefore' => '06:00:00',
    'TimeEditorActions' => new ArrayIterator($actionRows), 'TimeEditorConditionList' => new ArrayIterator($conditions)]);
$check($state()['operation']['status'] === 'draft_created', 'Time editor creates a rule with multiple conditions and a time window');
$fields = formFields(json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR)['actions']);
$timeDocument = json_decode($fields['YamlInput']['value'], true, 512, JSON_THROW_ON_ERROR);
$timeRule = $timeDocument['automations'][array_key_last($timeDocument['automations'])];
$check(count($timeRule['conditions']) === 3 && $timeRule['conditions'][0]['type'] === 'time_window', 'Time window and all listed conditions are serialized together');
$check($module->ValidateAutomationDraft() !== '' && $module->StoreAutomationDraft() !== '', 'Edited and time-triggered condition lists validate and persist');
$module->LoadAutomationEditor();
$module->SelectAutomation($timeRule['id']);
$fields = formFields(json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR)['actions']);
$check($fields['TimeEditorConditionList']['values'] === $conditions && $fields['TimeEditorWindow']['value'], 'Stored time rule restores its complete condition list and window');
$check(!$module->InspectEditorConditions(json_encode($conditions), 'edit', 'device'), 'A callback from the inactive section is rejected');

$debugEntries = array_map(static fn (array $entry): array => json_decode($entry[1], true, 512, JSON_THROW_ON_ERROR), $module->debugMessages);
$check(
    count(array_filter($debugEntries, static fn (array $entry): bool => str_contains($entry['Meldung'] ?? '', '(UND)'))) > 0
    && count(array_filter($debugEntries, static fn (array $entry): bool => ($entry['Bezug']['BedingungenVorbelegt'] ?? 0) >= 2)) > 0,
    'Safe German debug explains AND conditions and complete prefill'
);
