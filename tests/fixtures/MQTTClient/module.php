<?php

declare(strict_types=1);

class SyntheticMQTTClient extends IPSModuleStrict
{
    public array $sent = [];
    public bool $fail = false;
    public bool $replyImmediately = false;

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        $this->SetStatus(102);
    }

    public function ForwardData(string $JSONString): string
    {
        if ($this->fail) {
            throw new RuntimeException('Synthetic transport failure');
        }
        $packet = json_decode($JSONString, true, 512, JSON_THROW_ON_ERROR);
        if ($packet['DataID'] !== '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}' || $packet['PacketType'] !== 3
            || $packet['Retain'] !== false || $packet['QualityOfService'] !== 0
            || !ctype_xdigit($packet['Payload'])) {
            throw new RuntimeException('Invalid strict MQTT envelope');
        }
        $packet['Payload'] = json_decode(hex2bin($packet['Payload']), true, 512, JSON_THROW_ON_ERROR);
        $this->sent[] = $packet;
        if ($this->replyImmediately) {
            $this->publish(str_replace('/request/', '/response/', $packet['Topic']), [
                'transaction' => $packet['Payload']['transaction'], 'status' => 'ok', 'data' => ['healthy' => true]
            ]);
        }
        return '';
    }

    public function publish(string $topic, mixed $payload, bool $retain = false): void
    {
        $this->SendDataToChildren(json_encode([
            'DataID'     => '{7F7632D9-FA40-4F38-8DEA-C83CD4325A32}',
            'PacketType' => 3, 'QualityOfService' => 0, 'Retain' => $retain,
            'Topic'      => $topic, 'Payload' => bin2hex(json_encode($payload, JSON_THROW_ON_ERROR))
        ], JSON_THROW_ON_ERROR));
    }

    public function changeStatus(int $status): void
    {
        $this->SetStatus($status);
    }
}
