<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/helper/DataFlowHelper.php';
require_once __DIR__ . '/../libs/helper/ParentConnectionHelper.php';
require_once __DIR__ . '/../libs/helper/VariablePresentationHelper.php';
require_once __DIR__ . '/../libs/helper/ConfigurationFormHelper.php';
require_once __DIR__ . '/../libs/helper/DebugFlowHelper.php';
require_once __DIR__ . '/../libs/ModuleDiagnostics.php';
require_once __DIR__ . '/../libs/AutomaticStatusRefresh.php';
require_once __DIR__ . '/../libs/EditorGroupTargets.php';
require_once __DIR__ . '/../libs/Z2MInstanceDiscovery.php';
require_once __DIR__ . '/../libs/SymconEventImport.php';
require_once __DIR__ . '/../libs/Z2MProtocol.php';
require_once __DIR__ . '/../libs/ExtensionContract.php';
require_once __DIR__ . '/../libs/ExtensionIntegration.php';
require_once __DIR__ . '/../libs/AutomationEditor.php';
require_once __DIR__ . '/../libs/AutomationEditorManagement.php';
require_once __DIR__ . '/../libs/AutomationEditorSchedule.php';
require_once __DIR__ . '/../libs/AutomationEditorActions.php';
require_once __DIR__ . '/../libs/AutomationEditorAreas.php';
require_once __DIR__ . '/../libs/AutomationEditorTriggers.php';
require_once __DIR__ . '/../libs/AutomationEditorConditions.php';
require_once __DIR__ . '/../libs/EditorSymconVariables.php';
require_once __DIR__ . '/../libs/EditorPropertyCatalog.php';
require_once __DIR__ . '/../libs/EditorItemForms.php';

use Z2MAutomations\Z2MProtocol;

class Z2MAutomations extends IPSModuleStrict
{
    use Burki24\SymconModuleHelper\DataFlowHelper;
    use Burki24\SymconModuleHelper\ParentConnectionHelper;
    use Burki24\SymconModuleHelper\VariablePresentationHelper;
    use Burki24\SymconModuleHelper\ConfigurationFormHelper;
    use Burki24\SymconModuleHelper\DebugFlowHelper;
    use Z2MAutomations\ModuleDiagnostics;
    use Z2MAutomations\AutomaticStatusRefresh;
    use Z2MAutomations\EditorGroupTargets;
    use Z2MAutomations\Z2MInstanceDiscovery;
    use Z2MAutomations\SymconEventImport;
    use Z2MAutomations\ExtensionIntegration;
    use Z2MAutomations\AutomationEditor;
    use Z2MAutomations\AutomationEditorManagement;
    use Z2MAutomations\AutomationEditorSchedule;
    use Z2MAutomations\AutomationEditorActions;
    use Z2MAutomations\AutomationEditorAreas;
    use Z2MAutomations\AutomationEditorTriggers;
    use Z2MAutomations\AutomationEditorConditions;
    use Z2MAutomations\EditorSymconVariables;
    use Z2MAutomations\EditorPropertyCatalog;
    use Z2MAutomations\EditorItemForms;

    public function Create(): void
    {
        $this->TraceModuleCall(__FUNCTION__, __LINE__, function (): void
        {
            parent::Create();
            $this->RegisterPropertyBoolean('Active', false);
            $this->RegisterPropertyString('BaseTopic', 'zigbee2mqtt');
            $this->RegisterPropertyInteger('RequestTimeout', 10);
            $this->RegisterAttributeInteger('ObservedParentID', 0);
            $this->RegisterAttributeString('YamlDraft', '');
            $this->RegisterAttributeString('EditorSnapshot', '');
            $this->RegisterAttributeString('EditorDraft', '');
            $this->RegisterAttributeString('EditorSource', '');
            $this->RegisterAttributeString('EditorTarget', '');
            $this->RegisterAttributeString('EditorSelection', '');
            $this->RegisterAttributeString('EditorCondition', '');
            $this->RegisterAttributeString('EditorConditionProperty', '');
            $this->RegisterAttributeString('EditorModes', '');
            $this->RegisterAttributeBoolean('ShowYaml', false);
            $this->RegisterTimer('StatusRefresh', 0, 'Z2MA_RefreshStatus($_IPS[\'TARGET\']);');
            $this->RegisterTimer('RequestTimeout', 0, 'Z2MA_ExpireRequest($_IPS[\'TARGET\']);');
            $this->RegisterMessage($this->InstanceID, FM_CONNECT);
            $this->RegisterMessage($this->InstanceID, FM_DISCONNECT);
            $this->RegisterMessage(0, IPS_KERNELSTARTED);
            $this->RegisterVariableBoolean('MQTTConnected', $this->Translate('MQTT connected'), $this->BooleanPresentation($this->Translate('Connected'), $this->Translate('Disconnected')), 10);
            $this->RegisterVariableBoolean('BridgeOnline', $this->Translate('Zigbee2MQTT online'), $this->BooleanPresentation($this->Translate('Online'), $this->Translate('Offline or unknown')), 20);
            $this->RegisterVariableString('Z2MVersion', $this->Translate('Zigbee2MQTT version'), $this->TextPresentation(), 30);
            $this->RegisterVariableBoolean('ExtensionInstalled', $this->Translate('Automation extension installed'), $this->BooleanPresentation($this->Translate('Installed'), $this->Translate('Not detected')), 40);
            $this->RegisterVariableString('HealthStatus', $this->Translate('Health check'), $this->TextPresentation(), 50);
            $this->RegisterVariableBoolean('ExtensionReady', $this->Translate('Extension receiver confirmed'), $this->BooleanPresentation($this->Translate('Ready'), $this->Translate('Not confirmed')), 60);
            $this->RegisterVariableInteger('StoredRevision', $this->Translate('Stored YAML revision'), $this->ValuePresentation(), 70);
            $this->RegisterVariableString('OperationStatus', $this->Translate('Extension operation'), $this->TextPresentation(), 80);
            $this->RegisterVariableString('LastError', $this->Translate('Last extension error'), $this->TextPresentation(), 90);
            $this->RegisterVariableBoolean('ExecutionActive', $this->Translate('Execution confirmed active'), $this->BooleanPresentation($this->Translate('Active'), $this->Translate('Stopped or unknown')), 100);
            $this->RegisterVariableInteger('ActiveRevision', $this->Translate('Active automation revision'), $this->ValuePresentation(), 110);
            $this->RegisterVariableInteger('EnabledAutomations', $this->Translate('Enabled automations'), $this->ValuePresentation(), 120);
            $this->RegisterVariableString('ExecutionStatus', $this->Translate('Automation execution'), $this->TextPresentation(), 130);
            $this->RegisterVariableString('RuntimeError', $this->Translate('Last execution error'), $this->TextPresentation(), 140);
        });
    }

    public function ApplyChanges(): void
    {
        $this->TraceModuleCall(__FUNCTION__, __LINE__, function (): void
        {
            parent::ApplyChanges();
            $this->SetReceiveDataFilter($this->ReadPropertyBoolean('Active') ? Z2MProtocol::ReceiveFilter($this->ReadPropertyString('BaseTopic')) : Z2MProtocol::BLOCK_RECEIVE_FILTER);
            $this->WithTransportLock(function (): void
            {
                $this->ObserveParent();
                $this->SaveState($this->RefreshState());
            });
            $this->QueueAutomaticStatusRefresh('Einstellungen oder Modulupdate');
        });
    }

    public function GetCompatibleParents(): string
    {
        return $this->TraceModuleCall(__FUNCTION__, __LINE__, function ()
        {
            return json_encode(['type' => 'connect', 'moduleIDs' => [Z2MProtocol::MQTT_CLIENT, Z2MProtocol::MQTT_SERVER]], JSON_THROW_ON_ERROR);
        });
    }

    public function GetConfigurationForm(): string
    {
        return $this->TraceModuleCall(__FUNCTION__, __LINE__, function ()
        {
            $form = $this->LoadConfigurationForm();
            $state = $this->WithTransportLock(function (): array
            {
                $state = $this->RefreshState();
                $this->SaveState($state);
                return $state;
            });
            foreach ($this->EditorFormFields($form['actions']) as &$field) {
                if (($field['name'] ?? '') === 'YamlInput') {
                    $field['value'] = $this->ReadAttributeString('YamlDraft');
                    $field['visible'] = true;
                } elseif (($field['name'] ?? '') === 'ExecutionInfo') {
                    $field['caption'] = $this->ExecutionText($state);
                } elseif (($field['name'] ?? '') === 'RuntimeErrorInfo') {
                    $field['caption'] = $this->Translate(\Z2MAutomations\ExtensionContract::ErrorText($state['extension']['runtimeError'] ?? ''));
                } elseif (($field['name'] ?? '') === 'ActivateButton') {
                    $field['enabled'] = $state['runtimeConfirmed'] && ($state['extension']['storedRevision'] ?? 0) > 0 && $state['workflow'] === null;
                } elseif (($field['name'] ?? '') === 'StopButton') {
                    $field['enabled'] = $state['mqttConnected'];
                } elseif (($field['name'] ?? '') === 'ValidationInfo') {
                    $field['caption'] = $this->ValidationPreview($state);
                } elseif (($field['name'] ?? '') === 'ErrorInfo') {
                    $field['caption'] = $this->Translate(\Z2MAutomations\ExtensionContract::ErrorText($state['lastError']));
                } elseif (($field['name'] ?? '') === 'OperationInfo') {
                    $field['caption'] = $this->OperationText($state);
                }
            }
            unset($field);
            $this->FillEditorForm($form, $state);
            $this->PrepareEditorItemForms($form);
            $this->FillEventImportForm($form);
            $this->FillGatewayTopics($form);
            $this->QueueAutomaticStatusRefresh('Formular geöffnet');
            return $this->EncodeConfigurationForm($form);
        });
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        $this->TraceModuleCall(__FUNCTION__, __LINE__, function () use ($TimeStamp, $SenderID, $Message, $Data): void
        {
            if (($SenderID === $this->InstanceID && in_array($Message, [FM_CONNECT, FM_DISCONNECT], true))
                || ($SenderID === $this->ReadAttributeInteger('ObservedParentID') && $SenderID > 0 && in_array($Message, [IM_CHANGESTATUS, IM_DELETE], true))
                || ($SenderID === 0 && $Message === IPS_KERNELSTARTED)) {
                $this->WithTransportLock(function () use ($Message): void
                {
                    if ($Message === IPS_KERNELSTARTED) {
                        $this->SetBuffer('TransportState', '');
                    }
                    $this->ObserveParent();
                    $this->SaveState($this->RefreshState());
                });
                $this->QueueAutomaticStatusRefresh('Verbindung oder Kernelstart');
            }
        });
    }

    public function ReceiveData(string $JSONString): string
    {
        return $this->TraceModuleCall(__FUNCTION__, __LINE__, function () use ($JSONString)
        {
            try {
                if (strlen($JSONString) > Z2MProtocol::MAX_PAYLOAD_BYTES * 2 + 8192) {
                    $this->DebugEvent('FEHLER', 'MQTT-Nachricht wegen Übergröße verworfen.');
                    return '';
                }
                $packet = $this->DecodeDataFlowMessage($JSONString, Z2MProtocol::MQTT_RX);
                if (($packet['PacketType'] ?? null) !== 3 || !is_string($packet['Topic'] ?? null)
                    || !is_string($packet['Payload'] ?? null) || !is_bool($packet['Retain'] ?? null)) {
                    $this->DebugEvent('FEHLER', 'MQTT-Umschlag ungültig; PUBLISH mit Topic, HEX-Payload und Retain erwartet.');
                    return '';
                }
                $base = $this->ReadPropertyString('BaseTopic');
                $suffix = substr($packet['Topic'], strlen($base) + 1);
                if (!str_starts_with($packet['Topic'], $base . '/') || !in_array($suffix, Z2MProtocol::TOPICS, true)) {
                    $this->DebugEvent('AKTION', 'MQTT-Nachricht außerhalb der abonnierten Modultopics verworfen.');
                    return '';
                }
                $this->DebugEvent('EMPFANG', 'MQTT-PUBLISH empfangen.', ['Basistopic' => $base, 'Untertopic' => $suffix, 'Retain' => $packet['Retain'], 'Nutzdatenbytes' => intdiv(strlen($packet['Payload']), 2)]);
                $payload = Z2MProtocol::DecodePayload($packet['Payload']);
                $next = null;
                $refreshExtension = false;
                $this->WithTransportLock(function () use ($suffix, $payload, $packet, &$next, &$refreshExtension): void
                {
                    $state = $this->RefreshState();
                    if (!$state['mqttConnected']) {
                        $this->DebugEvent('AKTION', 'Empfang ohne aktive MQTT-Anbindung verworfen.');
                        $this->SaveState($state);
                        return;
                    }
                    if ($suffix === 'bridge/state') {
                        $bridge = is_object($payload) ? ($payload->state ?? null) : $payload;
                        if (in_array($bridge, ['online', 'offline'], true)) {
                            $state['bridgeState'] = $bridge;
                            if ($bridge === 'offline' && $state['pending'] !== null) {
                                $state['pending'] = null;
                                $state['health'] = 'cancelled';
                            }
                            if ($bridge === 'offline') {
                                $state['validation'] = null;
                                $state['extensionConfirmed'] = false;
                                $state['extension'] = null;
                                if ($state['workflow'] !== null) {
                                    $this->FinishExtensionWorkflow($state, 'cancelled', 'cancelled');
                                }
                            }
                        }
                    } elseif ($suffix === 'bridge/info' && $payload instanceof stdClass) {
                        if (!is_string($payload->version ?? null) || $payload->version === '' || strlen($payload->version) > 128) {
                            return;
                        }
                        $state['version'] = $payload->version;
                        $externalJS = $payload->config->advanced->enable_external_js ?? null;
                        $state['externalJS'] = is_bool($externalJS) ? $externalJS : null;
                    } elseif ($suffix === 'bridge/extensions' && is_array($payload)) {
                        $installed = false;
                        foreach ($payload as $extension) {
                            if (!$extension instanceof stdClass || !is_string($extension->name ?? null)) {
                                return;
                            }
                            $installed = $installed || $extension->name === 'z2m-automations.mjs';
                        }
                        $state['extensionInstalled'] = $installed;
                        if (!$installed) {
                            $state['extensionConfirmed'] = false;
                            $state['validation'] = null;
                            $state['extension'] = null;
                            if ($state['workflow'] !== null && $state['workflow']['kind'] !== 'install') {
                                $this->FinishExtensionWorkflow($state, 'cancelled', 'not_ready');
                            }
                        }
                    } elseif ($suffix === 'bridge/response/health_check' && !$packet['Retain'] && $payload instanceof stdClass
                        && $state['pending'] !== null && ($payload->transaction ?? null) === $state['pending']['transaction']) {
                        if (($payload->status ?? null) === 'error') {
                            $state['health'] = 'error';
                            $state['pending'] = null;
                        } elseif (($payload->status ?? null) === 'ok' && is_bool($payload->data->healthy ?? null)) {
                            $refreshExtension = ($state['pending']['refreshExtension'] ?? false) && $payload->data->healthy && $state['workflow'] === null;
                            $state['health'] = $payload->data->healthy ? 'healthy' : 'unhealthy';
                            $state['bridgeState'] = 'online';
                            $this->DebugEvent('AKTION', 'Frische passende Health-Antwort bestätigt die Erreichbarkeit von Zigbee2MQTT.', ['Gesund' => $payload->data->healthy]);
                            $state['pending'] = null;
                        }
                    } else {
                        $next = $this->ReceiveExtensionMessage($state, $suffix, $payload, $packet['Retain']);
                    }
                    $this->SaveState($state);
                });
                if ($next !== null) {
                    $this->DispatchExtensionFrame($next);
                } elseif ($refreshExtension) {
                    $this->DebugEvent('AKTION', 'Automatische Health-Prüfung bestätigt; Erweiterungsstatus einmalig abfragen.');
                    $this->StartExtensionWorkflow('status', '', '', false, true);
                }
            } catch (JsonException | InvalidArgumentException | UnexpectedValueException $exception) {
                $this->DebugEvent('FEHLER', 'Ungültige MQTT-Nachricht verworfen.', ['Ausnahmeklasse' => get_class($exception)]);
            }
            return '';
        });
    }

    public function GetTransportState(): string
    {
        return $this->TraceModuleCall(__FUNCTION__, __LINE__, function ()
        {
            return $this->WithTransportLock(function (): string
            {
                $state = $this->RefreshState();
                $this->SaveState($state);
                unset($state['scope'], $state['pending'], $state['workflow']);
                if ($state['extension'] !== null) {
                    unset($state['extension']['session']);
                }
                if ($state['validation'] !== null) {
                    unset($state['validation']['session'], $state['validation']['previousChecksum']);
                }
                return json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            });
        });
    }

    public function CheckHealth(bool $RefreshExtension = false): string
    {
        return $this->TraceModuleCall(__FUNCTION__, __LINE__, function () use ($RefreshExtension)
        {
            $request = $this->WithTransportLock(function () use ($RefreshExtension): array
            {
                $state = $this->RefreshState();
                if (!$state['mqttConnected'] || ($RefreshExtension && $state['workflow'] !== null)) {
                    $this->SaveState($state);
                    return [];
                }
                if ($state['pending'] !== null) {
                    return ['transaction' => $state['pending']['transaction'], 'send' => false];
                }
                $transaction = bin2hex(random_bytes(16));
                $state['pending'] = ['transaction' => $transaction, 'deadline' => $this->Now() + $this->ReadPropertyInteger('RequestTimeout')];
                if ($RefreshExtension) {
                    $state['pending']['refreshExtension'] = true;
                }
                $state['transaction'] = $transaction;
                $state['health'] = 'pending';
                $this->SaveState($state);
                return ['transaction' => $transaction, 'send' => true, 'scope' => $state['scope'], 'base' => $this->ReadPropertyString('BaseTopic')];
            });
            if ($request === []) {
                return '';
            }
            if (!$request['send']) {
                return $request['transaction'];
            }
            try {
                $current = $this->WithTransportLock(fn (): array => $this->RefreshState());
                if ($current['scope'] !== $request['scope'] || !$current['mqttConnected']
                    || ($current['pending']['transaction'] ?? null) !== $request['transaction']) {
                    return '';
                }
                $this->DebugEvent('SENDEN', 'Health-Anfrage an MQTT-Gateway übergeben; Antwort steht aus.', ['Gateway' => $this->GetParentID(), 'Topic' => $request['base'] . '/bridge/request/health_check', 'Anfragebezug' => substr(hash('sha256', $request['transaction']), 0, 16)]);
                // Publish outside the lock: a response can arrive during the parent call.
                $this->SendDataToParent($this->EncodeDataFlowMessage(Z2MProtocol::MQTT_TX, [
                    'PacketType' => 3, 'QualityOfService' => 0, 'Retain' => false,
                    'Topic'      => $request['base'] . '/bridge/request/health_check',
                    'Payload'    => bin2hex(json_encode(['transaction' => $request['transaction']], JSON_THROW_ON_ERROR))
                ]));
            } catch (Throwable $exception) {
                $this->WithTransportLock(function () use ($request): void
                {
                    $state = $this->RefreshState();
                    if (($state['pending']['transaction'] ?? null) === $request['transaction']) {
                        $state['pending'] = null;
                        $state['health'] = 'error';
                    }
                    $this->SaveState($state);
                });
                $this->DebugEvent('FEHLER', 'Health-Anfrage konnte nicht an das MQTT-Gateway übergeben werden.', ['Gateway' => $this->GetParentID()]);
                return '';
            }
            return $request['transaction'];
        });
    }

    public function ExpireRequest(): void
    {
        $this->TraceModuleCall(__FUNCTION__, __LINE__, function (): void
        {
            $this->WithTransportLock(function (): void
            {
                $this->SaveState($this->RefreshState());
            });
        });
    }

    protected function Now(): int
    {
        return time();
    }

    private function ObserveParent(): void
    {
        $previous = $this->ReadAttributeInteger('ObservedParentID');
        $parent = $this->GetParentID();
        if ($parent === $previous) {
            return;
        }
        foreach ([IM_CHANGESTATUS, IM_DELETE] as $message) {
            if ($previous > 0) {
                $this->UnregisterMessage($previous, $message);
            }
            if ($parent > 0) {
                $this->RegisterMessage($parent, $message);
            }
        }
        $this->WriteAttributeInteger('ObservedParentID', $parent);
    }

    private function ValidConfiguration(): bool
    {
        return Z2MProtocol::IsValidBaseTopic($this->ReadPropertyString('BaseTopic'))
            && $this->ReadPropertyInteger('RequestTimeout') >= 1 && $this->ReadPropertyInteger('RequestTimeout') <= 120;
    }

    private function RefreshState(): array
    {
        $parent = $this->GetParentID();
        $scope = json_encode([$parent, $this->ReadPropertyString('BaseTopic'), $this->ReadPropertyBoolean('Active'), $this->ReadPropertyInteger('RequestTimeout')], JSON_THROW_ON_ERROR);
        $connected = $this->ReadPropertyBoolean('Active') && $this->ValidConfiguration() && $this->HasParent()
            && in_array(IPS_GetInstance($parent)['ModuleInfo']['ModuleID'], [Z2MProtocol::MQTT_CLIENT, Z2MProtocol::MQTT_SERVER], true)
            && $this->HasActiveParent();
        $cached = $this->GetBuffer('TransportState');
        $state = $cached === '' ? [] : json_decode($cached, true, 32, JSON_THROW_ON_ERROR);
        if (($state['scope'] ?? null) !== $scope || (($state['mqttConnected'] ?? false) && !$connected)) {
            $cancelled = ($state['pending'] ?? null) !== null;
            $extensionCancelled = ($state['workflow'] ?? null) !== null;
            $state = ['scope' => $scope, 'mqttConnected' => false, 'bridgeState' => 'unknown', 'bridgeOnline' => false,
                'version'     => '', 'externalJS' => null, 'extensionInstalled' => false, 'pending' => null,
                'transaction' => '', 'health' => $cancelled ? 'cancelled' : 'idle'] + $this->NewExtensionState();
            if ($extensionCancelled) {
                $state['operation']['status'] = 'cancelled';
                $state['lastError'] = 'cancelled';
            }
        }
        $state['mqttConnected'] = $connected;
        $state['bridgeOnline'] = $connected && $state['bridgeState'] === 'online';
        if ($state['pending'] !== null && $state['pending']['deadline'] <= $this->Now()) {
            $state['pending'] = null;
            $state['health'] = 'timeout';
            $this->DebugEvent('FEHLER', 'Zeitlimit der Health-Anfrage ohne passende Antwort erreicht.');
        }
        return $this->RefreshExtensionWorkflow($state);
    }

    private function SaveState(array $state): void
    {
        $state['bridgeOnline'] = $state['mqttConnected'] && $state['bridgeState'] === 'online';
        $this->SetBuffer('TransportState', json_encode($state, JSON_THROW_ON_ERROR));
        $this->SetValue('MQTTConnected', $state['mqttConnected']);
        $this->SetValue('BridgeOnline', $state['bridgeOnline']);
        $this->SetValue('Z2MVersion', $state['version']);
        $this->SetValue('ExtensionInstalled', $state['extensionInstalled']);
        $this->SetValue('HealthStatus', $this->Translate(ucfirst($state['health'])));
        $this->SaveExtensionState($state);
        $deadlines = [];
        foreach ([$state['pending'], $state['workflow']] as $request) {
            if ($request !== null) {
                $deadlines[] = $request['deadline'];
            }
        }
        $this->SetTimerInterval('RequestTimeout', $deadlines === [] ? 0 : max(1, (min($deadlines) - $this->Now()) * 1000));
        $status = !$this->ReadPropertyBoolean('Active') ? 104 : (!$this->ValidConfiguration() ? 200 : (!$state['mqttConnected'] ? 201 : (!$state['bridgeOnline'] ? 202 : 102)));
        $this->SetStatus($status);
        $this->DebugEvent('AKTION', 'Transportstatus und Anzeigevariablen aktualisiert.', ['Gateway' => $this->GetParentID(), 'Aktiviert' => $this->ReadPropertyBoolean('Active'), 'KonfigurationGültig' => $this->ValidConfiguration(), 'Basistopic' => $this->ReadPropertyString('BaseTopic'), 'MQTTVerbunden' => $state['mqttConnected'], 'Z2MZustand' => $state['bridgeState'], 'Gesundheitsprüfung' => $state['health'], 'ExternesJavaScript' => $state['externalJS'], 'ErweiterungInstalliert' => $state['extensionInstalled'], 'Statuscode' => $status]);
    }

    private function WithTransportLock(callable $callback): mixed
    {
        $name = 'Z2MA.Transport.' . $this->InstanceID;
        if (!IPS_SemaphoreEnter($name, 1000)) {
            throw new RuntimeException('MQTT state is busy.');
        }
        try {
            return $callback();
        } finally {
            IPS_SemaphoreLeave($name);
        }
    }
}
