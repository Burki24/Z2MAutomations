<?php

declare(strict_types=1);

namespace Z2MAutomations;

use stdClass;
use Throwable;

trait ExtensionIntegration
{
    public function InstallExtension(): string
    {
        $code = @file_get_contents(__DIR__ . '/z2m/' . ExtensionContract::NAME, false, null, 0, Z2MProtocol::MAX_PAYLOAD_BYTES + 1);
        $valid = is_string($code) && $code !== '' && strlen($code) <= Z2MProtocol::MAX_PAYLOAD_BYTES && preg_match('//u', $code) === 1;
        return $this->StartExtensionWorkflow('install', '', $valid ? $code : '');
    }

    public function RequestExtensionStatus(): string
    {
        return $this->StartExtensionWorkflow('status');
    }

    public function ValidateYaml(string $Yaml): string
    {
        return $this->StartExtensionWorkflow('validate', $Yaml);
    }

    public function StoreYaml(string $Yaml): string
    {
        return $this->StartExtensionWorkflow('store', $Yaml);
    }

    private function NewExtensionState(): array
    {
        return ['extension' => null, 'extensionConfirmed' => false, 'workflow' => null, 'validation' => null,
            'operation'     => ['kind' => 'none', 'status' => 'idle', 'transaction' => ''], 'lastError' => ''];
    }

    private function StartExtensionWorkflow(string $kind, string $yaml = '', string $code = ''): string
    {
        $frame = $this->WithTransportLock(function () use ($kind, $yaml, $code): array
        {
            $state = $this->RefreshState();
            $checksum = strlen($yaml) <= ExtensionContract::MAX_YAML_BYTES ? hash('sha256', $yaml) : '';
            if ($state['workflow'] !== null) {
                if ($state['workflow']['kind'] === $kind && $state['workflow']['checksum'] === $checksum) {
                    return ['existing' => $state['workflow']['id']];
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
                $error = !$state['mqttConnected'] ? 'not_connected' : (!$state['bridgeOnline'] ? 'bridge_offline' : '');
            }
            if ($error === '' && $kind === 'install') {
                $error = $state['externalJS'] !== true ? 'external_js_disabled' : ($code === '' ? 'artifact_unavailable' : '');
            }
            if ($kind === 'store' && $error === '' && $state['validation'] === null) {
                $error = 'not_validated';
            }
            if ($error !== '') {
                $state['operation'] = ['kind' => $kind, 'status' => 'error', 'transaction' => ''];
                $state['lastError'] = $error;
                $this->SaveState($state);
                return [];
            }
            $id = bin2hex(random_bytes(16));
            $state['operation'] = ['kind' => $kind, 'status' => 'pending', 'transaction' => $id];
            $state['lastError'] = '';
            $state['workflow'] = ['id' => $id, 'kind' => $kind, 'checksum' => $checksum, 'yaml' => $yaml,
                'previousSession'      => $state['extension']['session'] ?? null, 'target' => null];
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
                return $state['scope'] === $frame['scope'] && $state['mqttConnected'] && $state['bridgeOnline']
                    && ($state['workflow']['transaction'] ?? null) === $frame['transaction'];
            });
            if (!$send) {
                return false;
            }
            if (strlen($frame['payload']) > Z2MProtocol::MAX_PAYLOAD_BYTES) {
                throw new \RuntimeException('Request too large');
            }
            // Never hold the state semaphore while the parent dispatches replies.
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
        $state['operation']['status'] = $status;
        $state['lastError'] = $error;
        $state['workflow'] = null;
    }

    private function ObserveExtensionStatus(array &$state, array $metadata): void
    {
        $prior = $state['extension'];
        $changedSession = $prior !== null && $prior['session'] !== $metadata['session'];
        if ($prior !== $metadata) {
            $state['validation'] = null;
            $state['extensionConfirmed'] = false;
        }
        if (($changedSession || !$metadata['ready']) && $state['workflow'] !== null && $state['workflow']['kind'] !== 'install') {
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
            return null;
        }
        $expected = $workflow['wire'] === 'install' ? 'bridge/response/extension/save' : ExtensionContract::PREFIX . 'response/' . $workflow['wire'];
        if ($suffix !== $expected) {
            return null;
        }
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
            $state['workflow']['target'] = $metadata;
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
        $state['validation'] = null;
        $this->FinishExtensionWorkflow($state, 'stored');
        return null;
    }

    private function ReceiptMatches(array $receipt, array $metadata): bool
    {
        return $receipt['session'] === $metadata['session'] && $receipt['expectedRevision'] === $metadata['storedRevision']
            && $receipt['previousChecksum'] === $metadata['checksum'];
    }

    private function RefreshExtensionWorkflow(array $state): array
    {
        $state += $this->NewExtensionState();
        if ($state['workflow'] !== null && $state['workflow']['deadline'] <= $this->Now()) {
            $this->FinishExtensionWorkflow($state, 'timeout', 'timeout');
            $state['extensionConfirmed'] = false;
        }
        return $state;
    }

    private function SaveExtensionState(array $state): void
    {
        $ready = $state['extensionConfirmed'] && ($state['extension']['ready'] ?? false);
        $this->SetValue('ExtensionReady', $ready);
        $this->SetValue('StoredRevision', $state['extension']['storedRevision'] ?? 0);
        $status = $this->OperationText($state);
        $error = $this->Translate(ExtensionContract::ErrorText($state['lastError']));
        $this->SetValue('OperationStatus', $status);
        $this->SetValue('LastError', $error);
        $this->UpdateFormField('OperationInfo', 'caption', $status);
        $this->UpdateFormField('ErrorInfo', 'caption', $error);
        $this->UpdateFormField('ValidationInfo', 'caption', $this->ValidationPreview($state));
    }

    private function OperationText(array $state): string
    {
        return $this->Translate(match ($state['operation']['status']) {
            'installed'       => 'Extension installed and receiver confirmed.',
            'validated'       => 'YAML validated. Review the preview before storing.',
            'stored'          => 'YAML stored. Automations are not executed.',
            'status_received' => 'Receiver status confirmed.',
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
