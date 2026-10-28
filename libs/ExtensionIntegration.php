<?php

declare(strict_types=1);

namespace Z2MAutomations;

use stdClass;
use Throwable;

trait ExtensionIntegration
{
    public function InstallExtension(bool $Notify = false): string
    {
        return $this->TraceModuleCall(__FUNCTION__, __LINE__, function () use ($Notify)
        {
            $code = @file_get_contents(__DIR__ . '/z2m/' . ExtensionContract::NAME, false, null, 0, Z2MProtocol::MAX_PAYLOAD_BYTES + 1);
            $valid = is_string($code) && $code !== '' && strlen($code) <= Z2MProtocol::MAX_PAYLOAD_BYTES && preg_match('//u', $code) === 1;
            return $this->StartExtensionWorkflow('install', '', $valid ? $code : '', $Notify);
        });
    }

    public function RequestExtensionStatus(): string
    {
        return $this->TraceModuleCall(__FUNCTION__, __LINE__, function ()
        {
            return $this->StartExtensionWorkflow('status');
        });
    }

    public function ValidateYaml(string $Yaml): string
    {
        return $this->TraceModuleCall(__FUNCTION__, __LINE__, function () use ($Yaml)
        {
            return $this->StartExtensionWorkflow('validate', $Yaml);
        });
    }

    public function StoreYaml(string $Yaml): string
    {
        return $this->TraceModuleCall(__FUNCTION__, __LINE__, function () use ($Yaml)
        {
            return $this->StartExtensionWorkflow('store', $Yaml);
        });
    }

    public function ActivateAutomations(): string
    {
        return $this->TraceModuleCall(__FUNCTION__, __LINE__, fn (): string => $this->StartExtensionWorkflow('activate'));
    }

    public function StopAutomations(): string
    {
        return $this->TraceModuleCall(__FUNCTION__, __LINE__, fn (): string => $this->StartExtensionWorkflow('deactivate'));
    }

    private function NewExtensionState(): array
    {
        return ['extension' => null, 'extensionConfirmed' => false, 'runtimeConfirmed' => false, 'workflow' => null, 'validation' => null,
            'operation'     => ['kind' => 'none', 'status' => 'idle', 'transaction' => ''], 'lastError' => ''];
    }

    private function StartExtensionWorkflow(string $kind, string $yaml = '', string $code = '', bool $notify = false): string
    {
        $this->DebugEvent('AKTION', 'Erweiterungsauftrag prüfen.', ['Auftrag' => $kind, 'YAMLBytes' => strlen($yaml), 'Artefaktbytes' => strlen($code)]);
        $frame = $this->WithTransportLock(function () use ($kind, $yaml, $code, $notify): array
        {
            $state = $this->RefreshState();
            if ($kind === 'deactivate' && $state['workflow'] !== null && $state['workflow']['kind'] !== 'deactivate') {
                $this->FinishExtensionWorkflow($state, 'cancelled', 'cancelled');
                $this->DebugEvent('AKTION', 'Stopp hat Vorrang; vorherigen Auftrag nicht erneut senden.');
            }
            $checksum = strlen($yaml) <= ExtensionContract::MAX_YAML_BYTES ? hash('sha256', $yaml) : '';
            if ($state['workflow'] !== null) {
                if ($state['workflow']['kind'] === $kind && $state['workflow']['checksum'] === $checksum) {
                    if ($notify) {
                        $state['workflow']['notify'] = true;
                        $this->SaveState($state);
                    }
                    return ['existing' => $state['workflow']['id']];
                }
                if ($notify) {
                    $this->NotifyExtensionInstallation('busy');
                }
                $state['lastError'] = 'busy';
                $this->SaveState($state);
                return [];
            }
            $error = '';
            if (in_array($kind, ['validate', 'store'], true)) {
                if (!ExtensionContract::ValidYamlText($yaml)) {
                    $error = 'invalid_yaml';
                } else {
                    $this->WriteAttributeString('YamlDraft', $yaml);
                    if (($state['validation']['checksum'] ?? null) !== $checksum) {
                        $state['validation'] = null;
                    }
                }
            }
            if ($error === '') {
                $error = !$state['mqttConnected'] ? 'not_connected' : (!$state['bridgeOnline'] && $kind !== 'deactivate' ? 'bridge_offline' : '');
            }
            if ($error === '' && $kind === 'install') {
                $error = $state['externalJS'] !== true ? 'external_js_disabled' : ($code === '' ? 'artifact_unavailable' : '');
            }
            if ($kind === 'store' && $error === '' && $state['validation'] === null) {
                $error = 'not_validated';
            }
            if ($error !== '') {
                $this->DebugEvent('FEHLER', 'Erweiterungsauftrag abgelehnt.', ['Auftrag' => $kind, 'Grund' => $error]);
                if ($notify) {
                    $this->NotifyExtensionInstallation($error);
                }
                $state['operation'] = ['kind' => $kind, 'status' => 'error', 'transaction' => ''];
                $state['lastError'] = $error;
                $this->SaveState($state);
                return [];
            }
            $id = bin2hex(random_bytes(16));
            $state['operation'] = ['kind' => $kind, 'status' => 'pending', 'transaction' => $id];
            $state['lastError'] = '';
            $state['workflow'] = ['id' => $id, 'kind' => $kind, 'checksum' => $checksum, 'yaml' => $yaml,
                'notify'               => $notify, 'previousSession'      => $state['extension']['session'] ?? null, 'target' => null, 'verifyRuntime' => false];
            if (in_array($kind, ['activate', 'deactivate'], true)) {
                $state['runtimeConfirmed'] = false;
            }
            if ($kind === 'install') {
                $state['extensionConfirmed'] = false;
                $state['validation'] = null;
            }
            if ($kind === 'validate') {
                $state['validation'] = null;
            }
            $frame = $kind === 'install'
                ? $this->ExtensionFrame($state, 'install', ['name' => ExtensionContract::NAME, 'code' => $code], $id)
                : $this->ExtensionFrame($state, 'status', [], $id);
            $this->SaveState($state);
            return $frame;
        });
        if ($frame === []) {
            return '';
        }
        if (isset($frame['existing'])) {
            return $frame['existing'];
        }
        return $this->DispatchExtensionFrame($frame) ? $frame['id'] : '';
    }

    private function ExtensionFrame(array &$state, string $wire, array $payload = [], ?string $transaction = null): array
    {
        $transaction ??= bin2hex(random_bytes(16));
        $state['workflow']['wire'] = $wire;
        $state['workflow']['transaction'] = $transaction;
        $state['workflow']['deadline'] = $this->Now() + $this->ReadPropertyInteger('RequestTimeout');
        if ($wire === 'store') {
            $state['runtimeConfirmed'] = false;
        }
        $payload = ['transaction' => $transaction] + $payload;
        if ($wire !== 'install') {
            $payload = ['protocol_version' => 1] + $payload;
        }
        return ['id'  => $state['workflow']['id'], 'transaction' => $transaction, 'scope' => $state['scope'],
            'topic'   => $this->ReadPropertyString('BaseTopic') . '/' . ($wire === 'install' ? 'bridge/request/extension/save' : ExtensionContract::PREFIX . 'request/' . $wire),
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
    }

    private function DispatchExtensionFrame(array $frame): bool
    {
        try {
            $send = $this->WithTransportLock(function () use ($frame): bool
            {
                $state = $this->RefreshState();
                $this->SaveState($state);
                return $state['scope'] === $frame['scope'] && $state['mqttConnected'] && ($state['bridgeOnline'] || ($state['workflow']['kind'] ?? '') === 'deactivate')
                    && ($state['workflow']['transaction'] ?? null) === $frame['transaction'];
            });
            if (!$send) {
                return false;
            }
            if (strlen($frame['payload']) > Z2MProtocol::MAX_PAYLOAD_BYTES) {
                throw new \RuntimeException('Request too large');
            }
            // Never hold the state semaphore while the parent dispatches replies.
            $this->DebugEvent('SENDEN', 'Erweiterungsanfrage an MQTT-Gateway übergeben; Bestätigung steht aus.', ['Gateway' => $this->GetParentID(), 'Topic' => $frame['topic'], 'Nutzdatenbytes' => strlen($frame['payload']), 'Anfragebezug' => substr(hash('sha256', $frame['transaction']), 0, 16)]);
            $this->SendDataToParent($this->EncodeDataFlowMessage(Z2MProtocol::MQTT_TX, [
                'PacketType' => 3, 'QualityOfService' => 0, 'Retain' => false,
                'Topic'      => $frame['topic'], 'Payload' => bin2hex($frame['payload'])
            ]));
            return true;
        } catch (Throwable $exception) {
            $this->WithTransportLock(function () use ($frame): void
            {
                $state = $this->RefreshState();
                if (($state['workflow']['transaction'] ?? null) === $frame['transaction']) {
                    $this->FinishExtensionWorkflow($state, 'error', 'send_failed');
                }
                $this->SaveState($state);
            });
            return false;
        }
    }

    private function FinishExtensionWorkflow(array &$state, string $status, string $error = ''): void
    {
        if ($error !== '' && in_array($state['workflow']['kind'] ?? '', ['activate', 'deactivate'], true)) {
            $state['runtimeConfirmed'] = false;
        }
        $this->DebugEvent($error === '' ? 'AKTION' : 'FEHLER', 'Erweiterungsauftrag abgeschlossen.', ['Auftrag' => $state['workflow']['kind'] ?? 'none', 'Ergebnis' => $status, 'Grund' => $error]);
        if (($state['workflow']['kind'] ?? '') === 'install' && ($state['workflow']['notify'] ?? false)) {
            $this->NotifyExtensionInstallation($error);
        }
        $state['operation']['status'] = $status;
        $state['lastError'] = $error;
        $state['workflow'] = null;
    }

    private function NotifyExtensionInstallation(string $error): void
    {
        try {
            $message = $error === '' ? $this->Translate('Extension installed or updated successfully. The receiver in Z2M has confirmed readiness.')
                : $this->Translate('Extension installation or update failed:') . ' ' . $this->Translate(ExtensionContract::ErrorText($error));
            $this->UpdateFormField('InstallResultMessage', 'caption', $message);
            $this->UpdateFormField('InstallResultPopup', 'visible', false);
            $this->UpdateFormField('InstallResultPopup', 'visible', true);
            $this->DebugEvent($error === '' ? 'AKTION' : 'FEHLER', 'Installationsrückmeldung an das geöffnete Formular übergeben.', ['Erfolgreich' => $error === '', 'Grund' => $error]);
        } catch (Throwable) {
            $this->DebugEvent('FEHLER', 'Installationsrückmeldung konnte im Formular nicht angezeigt werden. Statusanzeige und Debug beachten.');
        }
    }

    private function ObserveExtensionStatus(array &$state, array $metadata): void
    {
        $prior = $state['extension'];
        $changedSession = $prior !== null && $prior['session'] !== $metadata['session'];
        if ($prior !== $metadata) {
            $state['validation'] = null;
            $state['extensionConfirmed'] = false;
            $state['runtimeConfirmed'] = false;
        }
        $freshStopProbe = $state['workflow'] !== null && $state['workflow']['kind'] === 'deactivate'
            && $state['workflow']['wire'] === 'status' && !($state['workflow']['verifyRuntime'] ?? false);
        if (($changedSession || !$metadata['ready']) && $state['workflow'] !== null && $state['workflow']['kind'] !== 'install' && !$freshStopProbe) {
            $this->FinishExtensionWorkflow($state, 'cancelled', 'session_mismatch');
        }
        $state['extension'] = $metadata;
    }

    private function ReceiveExtensionMessage(array &$state, string $suffix, mixed $payload, bool $retained): ?array
    {
        if ($suffix === ExtensionContract::PREFIX . 'status') {
            $metadata = ExtensionContract::Status($payload);
            if ($metadata !== null) {
                $this->ObserveExtensionStatus($state, $metadata);
            }
            return null;
        }
        $workflow = $state['workflow'];
        if ($workflow === null || $retained || !$payload instanceof stdClass || ($payload->transaction ?? null) !== $workflow['transaction']) {
            $this->DebugEvent('AKTION', 'Antwort ohne passenden laufenden Auftrag verworfen.', ['Retain' => $retained, 'AuftragVorhanden' => $workflow !== null]);
            return null;
        }
        $expected = $workflow['wire'] === 'install' ? 'bridge/response/extension/save' : ExtensionContract::PREFIX . 'response/' . $workflow['wire'];
        if ($suffix !== $expected) {
            $this->DebugEvent('AKTION', 'Antworttopic passt nicht zur erwarteten Auftragsphase.');
            return null;
        }
        $this->DebugEvent('AKTION', 'Antwort dem laufenden Auftrag zugeordnet.', ['Phase' => $workflow['wire'], 'Anfragebezug' => substr(hash('sha256', $workflow['transaction']), 0, 16)]);
        if ($workflow['wire'] !== 'install' && ($payload->protocol_version ?? null) !== 1) {
            $state['extensionConfirmed'] = false;
            $state['validation'] = null;
            $this->FinishExtensionWorkflow($state, 'error', 'protocol_mismatch');
            return null;
        }
        if (($payload->status ?? null) === 'error') {
            $code = $workflow['wire'] === 'install' ? 'installation_failed' : ($payload->error->code ?? 'extension_error');
            if ($workflow['wire'] !== 'install' && (!is_string($code) || !in_array($code, ExtensionContract::ERROR_CODES, true))) {
                $code = 'extension_error';
            }
            if (in_array($code, ['session_mismatch', 'revision_conflict', 'revision_reused', 'storage_unavailable'], true)) {
                $state['validation'] = null;
                $state['extensionConfirmed'] = false;
            }
            $this->FinishExtensionWorkflow($state, 'error', $code);
            return null;
        }
        if (($payload->status ?? null) !== 'ok' || !($payload->data ?? null) instanceof stdClass) {
            $state['extensionConfirmed'] = false;
            $state['validation'] = null;
            $this->FinishExtensionWorkflow($state, 'error', 'invalid_response');
            return null;
        }
        if ($workflow['wire'] === 'install') {
            $state['extensionInstalled'] = true;
            return $this->ExtensionFrame($state, 'status');
        }
        if ($workflow['wire'] === 'status') {
            $metadata = ExtensionContract::Status($payload->data);
            if ($metadata === null || !$metadata['ready']) {
                $state['extensionConfirmed'] = false;
                $state['validation'] = null;
                $this->FinishExtensionWorkflow($state, 'error', $metadata === null ? 'protocol_mismatch' : 'not_ready');
                return null;
            }
            if ($workflow['kind'] === 'install' && $metadata['session'] === $workflow['previousSession']) {
                $this->FinishExtensionWorkflow($state, 'error', 'invalid_response');
                return null;
            }
            // A fresh, correlated status response authorizes only this snapshot.
            $receipt = $state['validation'];
            $state['extension'] = $metadata;
            $state['extensionConfirmed'] = true;
            $state['runtimeConfirmed'] = $metadata['runtimeVersion'] === 1;
            if (in_array($workflow['kind'], ['activate', 'deactivate'], true)) {
                $state['bridgeState'] = 'online';
            }
            $state['workflow']['target'] = $metadata;
            if (in_array($workflow['kind'], ['activate', 'deactivate'], true)) {
                if ($metadata['runtimeVersion'] !== 1) {
                    $this->FinishExtensionWorkflow($state, 'error', 'runtime_unavailable');
                    return null;
                }
                $wantActive = $workflow['kind'] === 'activate';
                if ($workflow['verifyRuntime']) {
                    if (!$this->RuntimeMatches($metadata, $workflow['target'], $wantActive)) {
                        $this->FinishExtensionWorkflow($state, 'error', 'runtime_mismatch');
                    } else {
                        $this->FinishExtensionWorkflow($state, $wantActive ? 'activated' : 'stopped');
                    }
                    return null;
                }
                if ($wantActive && ($metadata['storage'] !== 'ok' || $metadata['storedRevision'] === 0)) {
                    $this->FinishExtensionWorkflow($state, 'error', 'storage_unavailable');
                    return null;
                }
                $state['runtimeConfirmed'] = false;
                $command = ['session' => $metadata['session']];
                if ($wantActive) {
                    $token = $payload->data->activation_token ?? null;
                    if (!is_string($token) || preg_match('/\A[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}\z/D', $token) !== 1) {
                        $this->FinishExtensionWorkflow($state, 'error', 'activation_expired');
                        return null;
                    }
                    $command += ['activation_token' => $token, 'expected_revision' => $metadata['storedRevision'], 'checksum' => $metadata['checksum']];
                }
                return $this->ExtensionFrame($state, $workflow['kind'], $command);
            }
            if ($workflow['kind'] === 'editor') {
                return $this->ExtensionFrame($state, 'editor');
            }
            if ($workflow['kind'] === 'status' || $workflow['kind'] === 'install') {
                if ($receipt !== null && !$this->ReceiptMatches($receipt, $metadata)) {
                    $state['validation'] = null;
                }
                $this->FinishExtensionWorkflow(
                    $state,
                    $workflow['kind'] === 'install' ? 'installed' : 'status_received',
                    in_array($metadata['storage'], ['ok', 'empty'], true) ? '' : 'storage_unavailable'
                );
                return null;
            }
            if ($workflow['kind'] === 'validate') {
                return $this->ExtensionFrame($state, 'validate', ['yaml' => $workflow['yaml']]);
            }
            if ($receipt === null || $receipt['checksum'] !== $workflow['checksum'] || !$this->ReceiptMatches($receipt, $metadata)) {
                $state['validation'] = null;
                $this->FinishExtensionWorkflow($state, 'error', $receipt !== null && $receipt['session'] !== $metadata['session'] ? 'session_mismatch' : 'revision_conflict');
                return null;
            }
            if (!in_array($metadata['storage'], ['ok', 'empty'], true)) {
                $this->FinishExtensionWorkflow($state, 'error', 'storage_unavailable');
                return null;
            }
            $idempotent = $receipt['revision'] === $metadata['storedRevision'] && $receipt['checksum'] === $metadata['checksum'];
            if (!$idempotent && $receipt['revision'] !== $metadata['storedRevision'] + 1) {
                $this->FinishExtensionWorkflow($state, 'error', 'revision_reused');
                return null;
            }
            return $this->ExtensionFrame($state, 'store', ['yaml' => $workflow['yaml'], 'session' => $metadata['session'], 'expected_revision' => $metadata['storedRevision']]);
        }
        if (in_array($workflow['wire'], ['activate', 'deactivate'], true)) {
            $metadata = ExtensionContract::Status($payload->data);
            if ($metadata === null || !$this->RuntimeMatches($metadata, $workflow['target'], $workflow['wire'] === 'activate')) {
                $this->FinishExtensionWorkflow($state, 'error', 'runtime_mismatch');
                return null;
            }
            $state['extension'] = $metadata;
            $state['runtimeConfirmed'] = false;
            $state['workflow']['verifyRuntime'] = true;
            return $this->ExtensionFrame($state, 'status');
        }
        if ($workflow['wire'] === 'editor') {
            $this->ReceiveEditorData($state, $payload->data, $workflow['target']);
            return null;
        }
        if ($workflow['wire'] === 'validate') {
            $data = $payload->data;
            $target = $workflow['target'];
            if (($data->valid ?? null) !== true || !ExtensionContract::Revision($data->revision ?? null) || $data->revision < 1
                || ($data->checksum ?? null) !== $workflow['checksum'] || !is_int($data->automation_count ?? null)
                || $data->automation_count < 0 || $data->automation_count > 100 || ($data->executing ?? null) !== false
                || $target !== $state['extension']) {
                $state['validation'] = null;
                $state['extensionConfirmed'] = false;
                $this->FinishExtensionWorkflow($state, 'error', 'invalid_response');
                return null;
            }
            $state['validation'] = ['checksum' => $workflow['checksum'], 'revision' => $data->revision,
                'automationCount'              => $data->automation_count, 'session' => $target['session'],
                'expectedRevision'             => $target['storedRevision'], 'previousChecksum' => $target['checksum']];
            $this->FinishExtensionWorkflow($state, 'validated');
            return null;
        }
        $metadata = ExtensionContract::Storage($payload->data);
        $receipt = $state['validation'];
        if ($metadata === null || $receipt === null || $metadata['storedRevision'] !== $receipt['revision']
            || $metadata['checksum'] !== $workflow['checksum'] || $metadata['automationCount'] !== $receipt['automationCount']
            || $metadata['storage'] !== 'ok' || !is_bool($payload->data->unchanged ?? null)) {
            $state['extensionConfirmed'] = false;
            $state['validation'] = null;
            $this->FinishExtensionWorkflow($state, 'error', 'invalid_response');
            return null;
        }
        $state['extension'] = array_replace($workflow['target'], $metadata);
        if (!$metadata['executing']) {
            $state['extension']['activeRevision'] = null;
            $state['extension']['activeChecksum'] = null;
            $state['extension']['enabledCount'] = 0;
        }
        $state['extensionConfirmed'] = true;
        $state['runtimeConfirmed'] = $state['extension']['runtimeVersion'] === 1;
        $state['validation'] = null;
        $this->FinishExtensionWorkflow($state, 'stored');
        return null;
    }

    private function ReceiptMatches(array $receipt, array $metadata): bool
    {
        return $receipt['session'] === $metadata['session'] && $receipt['expectedRevision'] === $metadata['storedRevision']
            && $receipt['previousChecksum'] === $metadata['checksum'];
    }

    private function RuntimeMatches(array $metadata, array $target, bool $active): bool
    {
        return $metadata['ready'] && $metadata['runtimeVersion'] === 1 && $metadata['session'] === $target['session']
            && $metadata['executing'] === $active && (!$active || ($metadata['activeRevision'] === $target['storedRevision']
                && $metadata['activeChecksum'] === $target['checksum']));
    }

    private function RefreshExtensionWorkflow(array $state): array
    {
        $state += $this->NewExtensionState();
        if ($state['workflow'] !== null && $state['workflow']['deadline'] <= $this->Now()) {
            $this->FinishExtensionWorkflow($state, 'timeout', 'timeout');
            $state['extensionConfirmed'] = false;
        }
        if (!$state['mqttConnected'] || !$state['bridgeOnline'] || !$state['extensionConfirmed']) {
            $state['runtimeConfirmed'] = false;
        }
        return $state;
    }

    private function SaveExtensionState(array $state): void
    {
        $ready = $state['extensionConfirmed'] && ($state['extension']['ready'] ?? false);
        $this->SetValue('ExtensionReady', $ready);
        $this->SetValue('StoredRevision', $state['extension']['storedRevision'] ?? 0);
        $confirmed = $state['runtimeConfirmed'] && $state['extensionConfirmed'];
        $executing = $confirmed && ($state['extension']['executing'] ?? false);
        $this->DebugFlowWriteValue('ExecutionActive', $executing, __FUNCTION__, __LINE__);
        $this->DebugFlowWriteValue('ActiveRevision', $executing ? $state['extension']['activeRevision'] : 0, __FUNCTION__, __LINE__);
        $this->DebugFlowWriteValue('EnabledAutomations', $executing ? $state['extension']['enabledCount'] : 0, __FUNCTION__, __LINE__);
        $runtime = $this->ExecutionText($state);
        $this->DebugFlowWriteValue('ExecutionStatus', $runtime, __FUNCTION__, __LINE__);
        $runtimeError = $this->Translate(ExtensionContract::ErrorText($state['extension']['runtimeError'] ?? ''));
        $this->DebugFlowWriteValue('RuntimeError', $runtimeError, __FUNCTION__, __LINE__);
        $this->UpdateFormField('RuntimeErrorInfo', 'caption', $runtimeError);
        $this->UpdateFormField('ExecutionInfo', 'caption', $runtime);
        $this->UpdateFormField('ActivateButton', 'enabled', $confirmed && ($state['extension']['storedRevision'] ?? 0) > 0 && $state['workflow'] === null);
        $this->UpdateFormField('StopButton', 'enabled', $state['mqttConnected']);
        $status = $this->OperationText($state);
        $error = $this->Translate(ExtensionContract::ErrorText($state['lastError']));
        $this->SetValue('OperationStatus', $status);
        $this->SetValue('LastError', $error);
        $this->UpdateFormField('OperationInfo', 'caption', $status);
        $this->UpdateFormField('ErrorInfo', 'caption', $error);
        $this->UpdateFormField('ValidationInfo', 'caption', $this->ValidationPreview($state));
    }

    private function ExecutionText(array $state): string
    {
        if ($state['extensionConfirmed'] && ($state['extension']['runtimeVersion'] ?? 0) !== 1) {
            return $this->Translate('Update the bundled extension to use execution controls.');
        }
        if (!$state['runtimeConfirmed'] || !$state['extensionConfirmed']) {
            return $this->Translate('Execution state unknown. Refresh extension status.');
        }
        return !$state['extension']['executing'] ? $this->Translate('Automations are stopped in Z2M.')
            : sprintf(
                $this->Translate('Active revision %d with %d enabled automations. Z2M runs independently of Symcon.'),
                $state['extension']['activeRevision'],
                $state['extension']['enabledCount']
            );
    }

    private function OperationText(array $state): string
    {
        return $this->Translate(match ($state['operation']['status']) {
            'installed'       => 'Extension installed and receiver confirmed.',
            'validated'       => 'YAML validated. Review the preview before storing.',
            'stored'          => 'YAML stored. Activation is a separate step.',
            'status_received' => 'Receiver status confirmed.',
            'activated'       => 'Stored revision activated and confirmed by Z2M.',
            'stopped'         => 'Execution stopped and confirmed by Z2M.',
            'editor_loaded'   => 'Devices and stored automations loaded. Choose source and target.',
            'draft_created'   => 'Automation added to the draft. Validate, store and activate it next.',
            'rule_selected'   => 'Automation selected. Changes affect the local draft only.',
            'draft_updated'   => 'Automation updated in the draft. Validate and store it next.',
            'draft_deleted'   => 'Automation removed from the draft. Validate and store it next.',
            default           => ucfirst($state['operation']['status'])
        });
    }

    private function ValidationPreview(array $state): string
    {
        $receipt = $state['validation'];
        return $receipt === null ? $this->Translate('No current YAML validation.')
            : sprintf(
                $this->Translate('Validated revision %d, %d automations. Stored revision: %d. SHA-256: %s'),
                $receipt['revision'],
                $receipt['automationCount'],
                $receipt['expectedRevision'],
                $receipt['checksum']
            );
    }
}
