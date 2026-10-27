<?php

declare(strict_types=1);

namespace Z2MAutomations;

use InvalidArgumentException;

final class Z2MProtocol
{
    public const MQTT_CLIENT = '{F7A0DD2E-7684-95C0-64C2-D2A9DC47577B}';
    public const MQTT_SERVER = '{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}';
    public const MQTT_TX = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
    public const MQTT_RX = '{7F7632D9-FA40-4F38-8DEA-C83CD4325A32}';
    public const TOPICS = ['bridge/state', 'bridge/info', 'bridge/extensions', 'bridge/response/health_check',
        'bridge/response/extension/save', 'symcon/automations/status', 'symcon/automations/response/status',
        'symcon/automations/response/validate', 'symcon/automations/response/store'];
    public const MAX_PAYLOAD_BYTES = 1048576;
    // Valid JSON data-flow envelopes are never empty; no lookaround required.
    public const BLOCK_RECEIVE_FILTER = '^$';

    public static function IsValidBaseTopic(string $topic): bool
    {
        return $topic !== '' && strlen($topic) <= 1024 && trim($topic) === $topic
            && preg_match('//u', $topic) === 1 && !preg_match('/[\x00-\x1F\x7F+#]/', $topic)
            && !in_array('', explode('/', $topic), true);
    }

    public static function DecodePayload(string $hex): mixed
    {
        if ($hex === '' || strlen($hex) > self::MAX_PAYLOAD_BYTES * 2 || strlen($hex) % 2 !== 0 || !ctype_xdigit($hex)) {
            throw new InvalidArgumentException('Invalid strict MQTT payload');
        }
        $payload = hex2bin($hex);
        // Older Z2M versions publish bridge/state as plain text.
        if (in_array($payload, ['online', 'offline'], true)) {
            return $payload;
        }
        return json_decode($payload, false, 32, JSON_THROW_ON_ERROR);
    }

    public static function ReceiveFilter(string $baseTopic): string
    {
        if (!self::IsValidBaseTopic($baseTopic)) {
            return self::BLOCK_RECEIVE_FILTER;
        }
        $topics = [];
        foreach (self::TOPICS as $suffix) {
            foreach ([0, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE] as $flags) {
                $topics[] = preg_quote(json_encode($baseTopic . '/' . $suffix, $flags | JSON_THROW_ON_ERROR), '/');
            }
        }
        return '.*"Topic"\s*:\s*(?:' . implode('|', array_unique($topics)) . ').*';
    }
}
