<?php

declare(strict_types=1);

namespace Z2MAutomations;

use stdClass;

final class ExtensionContract
{
    public const NAME = 'z2m-automations.mjs';
    public const VERSION = '0.1.0';
    public const PREFIX = 'symcon/automations/';
    public const MAX_YAML_BYTES = 131072;
    public const ERROR_CODES = ['invalid_request', 'invalid_yaml', 'invalid_schema', 'invalid_semantics', 'session_mismatch', 'revision_conflict', 'revision_reused', 'storage_unavailable', 'storage_error', 'busy', 'not_ready', 'activation_expired', 'checksum_mismatch', 'unknown_entity', 'runtime_unavailable'];
    public const RUNTIME_ERRORS = ['', 'unknown_entity', 'action_failed', 'action_timeout', 'rate_limited'];

    public static function ValidYamlText(string $yaml): bool
    {
        return $yaml !== '' && strlen($yaml) <= self::MAX_YAML_BYTES && preg_match('//u', $yaml) === 1;
    }

    public static function Revision(mixed $value): bool
    {
        return is_int($value) && $value >= 0 && $value <= 9007199254740991;
    }

    public static function Checksum(mixed $value): bool
    {
        return is_string($value) && preg_match('/\A[a-f0-9]{64}\z/D', $value) === 1;
    }

    public static function Storage(mixed $data): ?array
    {
        if (!$data instanceof stdClass || !self::Revision($data->stored_revision ?? null)
            || !is_int($data->automation_count ?? null) || $data->automation_count < 0 || $data->automation_count > 100
            || !in_array($data->storage ?? null, ['empty', 'ok', 'recovered_read_only', 'error'], true)
            || !is_bool($data->executing ?? null) || !property_exists($data, 'checksum')) {
            return null;
        }
        if ($data->stored_revision === 0 ? ($data->checksum !== null || $data->automation_count !== 0) : !self::Checksum($data->checksum)) {
            return null;
        }
        if (($data->storage === 'empty' && $data->stored_revision !== 0)
            || (in_array($data->storage, ['ok', 'recovered_read_only'], true) && $data->stored_revision === 0)) {
            return null;
        }
        return ['storedRevision' => $data->stored_revision, 'checksum' => $data->checksum,
            'automationCount'    => $data->automation_count, 'storage' => $data->storage, 'executing' => $data->executing];
    }

    public static function Status(mixed $data): ?array
    {
        $storage = self::Storage($data);
        if ($storage === null || ($data->protocol_version ?? null) !== 1 || ($data->schema_version ?? null) !== 1
            || ($data->extension_version ?? null) !== self::VERSION || !is_string($data->session ?? null)
            || preg_match('/\A[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}\z/D', $data->session) !== 1
            || !in_array($data->state ?? null, ['ready', 'stopped'], true)) {
            return null;
        }
        $runtime = ['runtimeVersion' => 0, 'activeRevision' => null, 'activeChecksum' => null, 'enabledCount' => 0, 'runtimeError' => ''];
        if (property_exists($data, 'runtime_version')) {
            if ($data->runtime_version !== 1 || !property_exists($data, 'active_revision') || !property_exists($data, 'active_checksum')
                || !is_int($data->enabled_count ?? null) || $data->enabled_count < 0 || $data->enabled_count > $data->automation_count
                || !in_array($data->runtime_error ?? null, self::RUNTIME_ERRORS, true)
                || ($data->executing ? ($data->active_revision !== $data->stored_revision || $data->active_checksum !== $data->checksum || $data->stored_revision === 0)
                    : ($data->active_revision !== null || $data->active_checksum !== null || $data->enabled_count !== 0))) {
                return null;
            }
            $runtime = ['runtimeVersion' => 1, 'activeRevision' => $data->active_revision, 'activeChecksum' => $data->active_checksum,
                'enabledCount'           => $data->enabled_count, 'runtimeError' => $data->runtime_error];
        } elseif ($data->executing) {
            return null;
        }
        return $storage + $runtime + ['session' => $data->session, 'ready' => $data->state === 'ready', 'version' => $data->extension_version];
    }

    public static function ErrorText(string $code): string
    {
        return match ($code) {
            ''                     => '',
            'not_connected'        => 'No active MQTT connection.',
            'bridge_offline'       => 'Zigbee2MQTT must report online first.',
            'external_js_disabled' => 'External JavaScript is disabled or has not been confirmed by bridge/info.',
            'artifact_unavailable' => 'The bundled extension could not be read.',
            'installation_failed'  => 'Zigbee2MQTT rejected the extension installation.',
            'send_failed'          => 'The MQTT request could not be sent.',
            'timeout'              => 'No matching response arrived before the deadline. Check the remote state before retrying.',
            'cancelled'            => 'The operation was cancelled because its connection or extension session changed.',
            'busy'                 => 'Another extension operation is still pending.',
            'invalid_response'     => 'The response does not match the expected protocol or content.',
            'protocol_mismatch'    => 'The receiver protocol or extension version is not supported.',
            'not_validated'        => 'Validate this exact YAML successfully before storing it.',
            'invalid_request'      => 'Invalid request envelope.',
            'invalid_yaml'         => 'Invalid or oversized YAML text.',
            'invalid_schema'       => 'The YAML does not conform to automation schema version 1.',
            'invalid_semantics'    => 'The YAML contains duplicate IDs, an invalid timezone or an empty time window.',
            'session_mismatch'     => 'The extension restarted. Validate the YAML again.',
            'revision_conflict'    => 'The stored revision changed. Refresh the state and validate the YAML again.',
            'revision_reused'      => 'Use the next revision or an unchanged copy of the stored YAML.',
            'storage_unavailable'  => 'The receiver storage is unavailable or read-only.',
            'storage_error'        => 'The receiver could not persist the YAML.',
            'not_ready'            => 'The extension is not ready to accept requests.',
            'activation_expired'   => 'The activation approval expired. Refresh the state and activate again.',
            'checksum_mismatch'    => 'The stored configuration changed before activation.',
            'unknown_entity'       => 'A configured device or group is unavailable in Z2M.',
            'runtime_unavailable'  => 'Update the bundled extension to use execution controls.',
            'runtime_mismatch'     => 'Z2M did not confirm the requested execution state. Refresh the status.',
            'action_failed'        => 'Z2M could not hand over an action. The remaining sequence was cancelled.',
            'action_timeout'       => 'An action handover timed out. No automatic retry was started.',
            'rate_limited'         => 'The action rate limit was reached. Excess actions were cancelled.',
            'editor_unavailable'   => 'Load the devices and stored configuration into the editor first.',
            'editor_missing'       => 'The selected automation is no longer in the draft. Select it again.',
            'editor_advanced'      => 'This advanced rule supports name and enabled state changes only in the click editor.',
            'editor_invalid'       => 'Choose a name, source, trigger, target and action from the available options.',
            'editor_changed'       => 'The configuration or draft changed. Reload the editor before adding a rule.',
            default                => 'The extension reported an unspecified error.'
        };
    }
}
