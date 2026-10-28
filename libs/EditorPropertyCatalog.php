<?php

declare(strict_types=1);

namespace Z2MAutomations;

trait EditorPropertyCatalog
{
    private function ReadEditorProperties(object $device, array $entry): ?array
    {
        $properties = [];
        if (isset($device->properties)) {
            if (!is_array($device->properties) || count($device->properties) > 64) {
                return null;
            }
            foreach ($device->properties as $p) {
                if (!is_object($p) || !is_string($p->property ?? null) || preg_match('/\A[A-Za-z][A-Za-z0-9_]{0,63}\z/D', $p->property) !== 1
                    || in_array($p->property, ['constructor', 'prototype', '__proto__'], true) || isset($properties[$p->property])
                    || !in_array($p->type ?? '', ['binary', 'enum', 'numeric'], true) || !is_int($p->access ?? null) || $p->access < 1 || $p->access > 3
                    || !is_string($p->label ?? null) || strlen($p->label) > 320 || preg_match('/[\x00-\x1f\x7f]/', $p->label)
                    || !is_string($p->endpoint ?? null) || ($p->endpoint !== '' && preg_match('/\A[A-Za-z0-9_-]{1,32}\z/D', $p->endpoint) !== 1)
                    || !is_array($p->values ?? null) || count($p->values) > 32) {
                    return null;
                }
                $meta = ['property' => $p->property, 'type' => $p->type, 'access' => $p->access, 'label' => $p->label, 'endpoint' => $p->endpoint, 'values' => $p->values];
                foreach ($p->values as $value) {
                    if (!(is_scalar($value) || $value === null) || (is_string($value) && strlen($value) > 1024)) {
                        return null;
                    }
                }
                foreach (['minimum', 'maximum', 'step'] as $key) {
                    if (isset($p->$key)) {
                        if (!(is_float($p->$key) || is_int($p->$key)) || !is_finite((float) $p->$key) || ($key === 'step' && $p->$key <= 0)) {
                            return null;
                        }
                        $meta[$key] = $p->$key;
                    }
                }
                if (isset($meta['minimum'], $meta['maximum']) && $meta['minimum'] > $meta['maximum']) {
                    return null;
                }
                $properties[$p->property] = $meta;
            }
        } else {
            foreach (['triggers' => 1, 'actions' => 2] as $kind => $access) {
                foreach ($entry[$kind] as $choice) {
                    $property = $choice['property'];
                    $properties[$property] ??= ['property' => $property, 'type' => 'enum', 'access' => 0, 'label' => $property, 'endpoint' => '', 'values' => []];
                    $properties[$property]['access'] |= $access;
                    if (!in_array($choice['value'], $properties[$property]['values'], true)) {
                        $properties[$property]['values'][] = $choice['value'];
                    }
                }
            }
        }
        return $properties;
    }

    private function EditorProperties(array $snapshot, string $device, string $side): array
    {
        return array_filter($snapshot['devices'][$device]['properties'] ?? [], fn (array $p): bool => ($p['access'] & ($side === 'Target' ? 2 : 1)) !== 0
            && ($side !== 'Target' || $this->EditorActionVariable($snapshot, $device, $p['property']) !== null));
    }

    private function EditorPropertyLabel(array $snapshot, string $device, string $property, string $side): string
    {
        if ($side === 'Target') {
            $binding = $this->EditorActionVariable($snapshot, $device, $property);
            if ($binding !== null) {
                return $binding['name'] . ' [' . $property . ']';
            }
        }
        $meta = $snapshot['devices'][$device]['properties'][$property] ?? [];
        $label = $meta['label'] ?? $property;
        $label = $this->Translate(match ($label) {
            'state' => 'State', 'action' => 'Button event', 'occupancy' => 'Motion', 'contact' => 'Contact', default => $label
        });
        $endpoint = $meta['endpoint'] ?? '';
        if ($endpoint !== '') {
            $endpoint = preg_match('/\Al[0-9]+\z/D', $endpoint) ? strtoupper($endpoint) : $endpoint;
            return $label . ' (' . sprintf($this->Translate('Channel %s'), $endpoint) . ')';
        }
        return $label;
    }

    private function EditorValueAllowed(array $snapshot, string $device, string $property, mixed $value, string $side): bool
    {
        $meta = $this->EditorProperties($snapshot, $device, $side)[$property] ?? null;
        if ($meta === null) {
            return false;
        }
        if ($meta['type'] !== 'numeric') {
            return in_array($value, $meta['values'], true);
        }
        // A source comparison threshold is not a device command: it may lie between
        // report steps or outside the sensor's measurement range.
        if ($side === 'Source') {
            return (is_int($value) || is_float($value)) && is_finite((float) $value);
        }
        if (!(is_float($value) || is_int($value)) || !is_finite((float) $value)
            || (isset($meta['minimum']) && $value < $meta['minimum']) || (isset($meta['maximum']) && $value > $meta['maximum'])) {
            return false;
        }
        if (isset($meta['step'])) {
            $step = ($value - ($meta['minimum'] ?? 0)) / $meta['step'];
            if (abs($step - round($step)) > 0.00000001 * max(1, abs($step))) {
                return false;
            }
        }
        return true;
    }

    private function EditorValueKey(array $snapshot, string $device, string $property, mixed $value, string $side): ?string
    {
        if (!$this->EditorValueAllowed($snapshot, $device, $property, $value, $side)) {
            return null;
        }
        foreach ($snapshot['devices'][$device][$side === 'Target' ? 'actions' : 'triggers'] ?? [] as $key => $choice) {
            if ($choice['property'] === $property && $choice['value'] === $value) {
                return $key;
            }
        }
        return 'value.' . rtrim(strtr(base64_encode(json_encode(['property' => $property, 'value' => $value], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    private function DecodeEditorValue(array $snapshot, string $device, string $key, string $side): ?array
    {
        if (!str_starts_with($key, 'value.') || strlen($key) > 2048) {
            return null;
        }
        $raw = base64_decode(strtr(substr($key, 6), '-_', '+/'), true);
        $choice = $raw === false ? null : json_decode($raw, true, 8);
        return is_array($choice) && count($choice) === 2 && is_string($choice['property'] ?? null) && array_key_exists('value', $choice)
            && $this->EditorValueAllowed($snapshot, $device, $choice['property'], $choice['value'], $side) ? $choice : null;
    }
}
