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
    public const ERROR_CODES = ['invalid_request', 'invalid_yaml', 'invalid_schema', 'invalid_semantics', 'session_mismatch', 'revision_conflict', 'revision_reused', 'storage_unavailable', 'storage_error', 'busy', 'not_ready'];

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
        return $storage + ['session' => $data->session, 'ready' => $data->state === 'ready', 'version' => $data->extension_version];
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
            default                => 'The extension reported an unspecified error.'
        };
    }
}
