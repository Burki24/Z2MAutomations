<?php

declare(strict_types=1);

namespace Z2MAutomations;

trait AutomaticStatusRefresh
{
    public function RefreshStatus(): void
    {
        $this->TraceModuleCall(__FUNCTION__, __LINE__, function (): void
        {
            $start = $this->WithTransportLock(function (): bool
            {
                $this->SetTimerInterval('StatusRefresh', 0);
                $state = $this->RefreshState();
                $automatic = $this->AutomaticStatusState();
                $queued = ($automatic['queued'] ?? false) && ($automatic['scope'] ?? '') === $state['scope'];
                $automatic['queued'] = false;
                $start = $queued && IPS_GetKernelRunlevel() === KR_READY && $state['mqttConnected'] && $state['pending'] === null && $state['workflow'] === null;
                if ($start) {
                    $automatic['last'] = $this->Now();
                }
                $this->SetBuffer('AutomaticStatusRefresh', json_encode($automatic, JSON_THROW_ON_ERROR));
                $this->SaveState($state);
                $this->DebugEvent('AKTION', $start ? 'Einmalige automatische Statusprüfung gestartet; zuerst Zigbee2MQTT, danach Erweiterung prüfen.'
                    : 'Automatische Statusprüfung nicht gestartet; kein gültiger Auftrag, keine Verbindung oder anderer Auftrag läuft.');
                return $start;
            });
            if ($start) {
                $this->CheckHealth(true);
            }
        });
    }

    private function AutomaticStatusState(): array
    {
        $text = $this->GetBuffer('AutomaticStatusRefresh');
        return $text === '' ? [] : json_decode($text, true, 8, JSON_THROW_ON_ERROR);
    }

    private function QueueAutomaticStatusRefresh(string $reason): void
    {
        $this->WithTransportLock(function () use ($reason): void
        {
            $state = $this->RefreshState();
            if (IPS_GetKernelRunlevel() !== KR_READY || !$state['mqttConnected']) {
                $this->SetTimerInterval('StatusRefresh', 0);
                $this->SetBuffer('AutomaticStatusRefresh', '');
                $this->DebugEvent('AKTION', 'Automatische Statusprüfung ohne bereiten Kernel oder aktive MQTT-Anbindung nicht eingeplant.', ['Anlass' => $reason]);
                return;
            }
            $automatic = $this->AutomaticStatusState();
            if (($automatic['scope'] ?? '') !== $state['scope']) {
                $automatic = ['scope' => $state['scope'], 'queued' => false, 'last' => null];
            }
            if ($automatic['queued'] || ($automatic['last'] !== null && $this->Now() - $automatic['last'] < 10)
                || $state['pending'] !== null || $state['workflow'] !== null) {
                $this->DebugEvent('AKTION', 'Zusätzliche automatische Statusprüfung zusammengefasst oder wegen laufendem Auftrag ausgelassen.', ['Anlass' => $reason]);
                return;
            }
            $automatic['queued'] = true;
            $this->SetBuffer('AutomaticStatusRefresh', json_encode($automatic, JSON_THROW_ON_ERROR));
            $this->SetTimerInterval('StatusRefresh', 1000);
            $this->DebugEvent('AKTION', 'Einmalige Statusprüfung für die Zeit nach dem Formularaufbau eingeplant.', ['Anlass' => $reason]);
        });
    }
}
