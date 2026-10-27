<?php

declare(strict_types=1);

namespace Z2MAutomations;

trait ModuleDiagnostics
{
    private function TraceModuleCall(string $function, int $line, callable $callback): mixed
    {
        $void = in_array($function, ['Create', 'ApplyChanges', 'MessageSink', 'ExpireRequest', 'SelectEditorDevice', 'ShowYamlEditor'], true);
        $results = $function === 'CreateSimpleAutomation'
            ? ['Keine Automation hinzugefügt; Ablehnungsgrund siehe Ablauf.', 'Automation zum lokalen Entwurf hinzugefügt; Z2M noch unverändert.'] : null;
        return $this->DebugFlowCall($function, $line, \Closure::fromCallable($callback), $void, [], $results);
    }

    private function DebugEvent(string $event, string $message, array $details = []): void
    {
        $source = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $this->DebugFlowEvent($source[1]['function'] ?? __FUNCTION__, $source[0]['line'] ?? __LINE__, $event, $message, $details);
    }
}
