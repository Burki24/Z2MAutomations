<?php

declare(strict_types=1);

namespace Z2MAutomations;

use Throwable;

trait ModuleDiagnostics
{
    private array $debugCalls = [];
    private int $debugSequence = 0;

    private function TraceModuleCall(string $function, int $line, callable $callback): mixed
    {
        $root = $this->debugCalls === [];
        $operation = $root ? $this->InstanceID . '-' . hrtime(true) : $this->debugCalls[0]['operation'];
        $this->debugCalls[] = ['operation' => $operation, 'call' => ++$this->debugSequence, 'function' => $function, 'line' => $line];
        $this->DebugEvent('START', 'Aufruf begonnen.');
        $completed = false;
        try {
            $result = $callback();
            $completed = true;
            return $result;
        } catch (Throwable $exception) {
            $this->DebugEvent('FEHLER', 'Aufruf durch Ausnahme abgebrochen.', ['Ausnahmeklasse' => get_class($exception), 'Quellzeile' => $exception->getLine()]);
            throw $exception;
        } finally {
            $this->DebugEvent('ENDE', $completed ? 'Aufruf beendet; fachliches Ergebnis siehe Einzelmeldungen.' : 'Aufruf abgebrochen.');
            array_pop($this->debugCalls);
        }
    }

    private function DebugEvent(string $event, string $message, array $details = []): void
    {
        try {
            $frame = $this->debugCalls === [] ? ['operation' => $this->InstanceID . '-' . hrtime(true), 'call' => 0, 'function' => __FUNCTION__, 'line' => __LINE__] : $this->debugCalls[array_key_last($this->debugCalls)];
            $source = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $line = $source[0]['line'] ?? $frame['line'];
            $function = $source[1]['function'] ?? $frame['function'];
            if (in_array($function, ['{closure}', 'TraceModuleCall'], true)) {
                $function = $frame['function'];
            }
            $this->SendSafeDebug('Z2MAutomations | ' . $event . ' | ' . $function . ':' . $line, [
                'Instanz' => $this->InstanceID, 'Vorgang' => $frame['operation'], 'Aufruf' => $frame['call'],
                'Meldung' => $message, 'Bezug' => $details
            ], 4096, ['session', 'yaml', 'code', 'payload']);
        } catch (Throwable) {
            // Diagnostics must never affect the data flow or its error handling.
        }
    }
}
