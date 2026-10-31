<?php

declare(strict_types=1);

require __DIR__ . '/stubs/autoload.php';

$check = static function (bool $value, string $message): void
{
    if (!$value) {
        throw new RuntimeException($message);
    }
};
IPS\Kernel::reset();
$root = dirname(__DIR__);
$library = json_decode(file_get_contents($root . '/library.json'), true, 512, JSON_THROW_ON_ERROR);
foreach ([$root . '/Z2MAutomations', __DIR__ . '/fixtures/MQTTClient', __DIR__ . '/fixtures/Z2MDevice'] as $path) {
    IPS\ModuleLoader::loadSingleModule($path, $library['id']);
}
$metadata = json_decode(file_get_contents($root . '/Z2MAutomations/module.json'), true, 512, JSON_THROW_ON_ERROR);
class GatewayTopicsModule extends Z2MAutomations
{
    public array $updates = [];
    public array $debugMessages = [];
    public function SeedDraft(): void
    {
        $this->WriteAttributeString('YamlDraft', 'synthetic-unsaved-draft');
    }
    public function Draft(): string
    {
        return $this->ReadAttributeString('YamlDraft');
    }
    public function RefreshInterval(): int
    {
        return $this->GetTimerInterval('StatusRefresh');
    }
    protected function LoadConfigurationForm(): array
    {
        return json_decode(file_get_contents(dirname(__DIR__) . '/Z2MAutomations/form.json'), true, 512, JSON_THROW_ON_ERROR);
    }
    protected function UpdateFormField(string $Field, string $Parameter, mixed $Value): bool
    {
        if ($Field === 'DetectedBaseTopic' && $Parameter === 'value') {
            throw new RuntimeException('Native MultiSelect value must follow its BaseTopic binding; a JSON string becomes an unavailable scalar selection');
        }
        $this->updates[$Field][$Parameter] = $Value;
        return true;
    }
    protected function SendDebug(string $Message, string $Data, int $Format): bool
    {
        $this->debugMessages[] = $Data;
        return true;
    }
}
$registry = new ReflectionProperty(IPS\ModuleLoader::class, 'modules');
$modules = $registry->getValue();
$modules[$metadata['id']]['Class'] = GatewayTopicsModule::class;
$registry->setValue(null, $modules);
$gateways = [];
foreach (['synthetic/first', 'synthetic/second'] as $topic) {
    $gateway = IPS_CreateInstance('{F7A0DD2E-7684-95C0-64C2-D2A9DC47577B}');
    IPS_ApplyChanges($gateway);
    $device = IPS_CreateInstance('{E5BB36C6-A70B-EB23-3716-9151A09AC8A2}');
    IPS_ConnectInstance($device, $gateway);
    IPS_SetProperty($device, 'MQTTBaseTopic', $topic);
    IPS_SetProperty($device, 'IEEE', '0x0000000000000001');
    IPS_ApplyChanges($device);
    $gateways[] = $gateway;
}
$id = IPS_CreateInstance($metadata['id']);
IPS_ConnectInstance($id, $gateways[0]);
IPS_SetProperty($id, 'Active', true);
IPS_SetProperty($id, 'BaseTopic', 'synthetic/first');
IPS_ApplyChanges($id);
$module = IPS\InstanceManager::getInstanceInterface($id);
$fields = static function (array $items) use (&$fields): array
{
    $result = [];
    foreach ($items as $item) {
        if (isset($item['name'])) {
            $result[$item['name']] = $item;
        }
        $result += $fields($item['items'] ?? []);
    }
    return $result;
};
$form = $fields(json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR)['elements']);
foreach ($form['DetectedBaseTopic']['options'] as $option) {
    $check(array_column($option['value'], 'name') === ['BaseTopic'], 'Every native multi-select option must assign the same nonempty property set');
}
$module->SeedDraft();
$check($module->CheckHealth() !== '', 'An old-scope health request exists before changing gateways');
IPS_DisconnectInstance($id);
IPS_ConnectInstance($id, $gateways[1]);
$module->MessageSink(time(), $id, FM_CONNECT, []);
$options = json_decode($module->updates['DetectedBaseTopic']['options'] ?? '[]', true, 32, JSON_THROW_ON_ERROR);
$check(in_array('synthetic/second', array_column($options, 'caption'), true), 'Gateway change immediately updates topic suggestions without reopening the form');
$check(!in_array('synthetic/first', array_column($options, 'caption'), true), 'Previous gateway topics are not offered as detected topics on the new gateway');
$current = [['name' => 'BaseTopic', 'value' => 'synthetic/first']];
$check(in_array($current, array_column($options, 'value'), true), 'The configured topic stays representable and never becomes an unavailable [] value');
$check(IPS_GetProperty($id, 'BaseTopic') === 'synthetic/first' && $module->Draft() === 'synthetic-unsaved-draft', 'Gateway changes do not silently overwrite settings or drafts');
$check($module->RefreshInterval() === 0, 'Automatic probes wait when the saved topic does not match known topics on the new gateway');
$changedState = json_decode($module->GetTransportState(), true, 32, JSON_THROW_ON_ERROR);
$check(!$changedState['bridgeOnline'] && $changedState['health'] !== 'pending', 'Gateway changes invalidate the previous gateway status and outstanding health request');
IPS_SetProperty($id, 'BaseTopic', 'synthetic/second');
IPS_ApplyChanges($id);
$form = $fields(json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR)['elements']);
$check(!array_key_exists('value', $form['DetectedBaseTopic'])
    && in_array([['name' => 'BaseTopic', 'value' => 'synthetic/second']], array_column($form['DetectedBaseTopic']['options'], 'value'), true), 'Native selection follows BaseTopic without an explicit value override');
$check($module->RefreshInterval() === 1000, 'The newly applied scope queues its one-shot health check');
$parent = IPS\InstanceManager::getInstanceInterface($gateways[1]);
$check($module->CheckHealth() !== '' && str_starts_with($parent->sent[array_key_last($parent->sent)]['Topic'], 'synthetic/second/'), 'The health probe is routed to the newly applied topic and gateway');
$extraDevice = IPS_CreateInstance('{E5BB36C6-A70B-EB23-3716-9151A09AC8A2}');
IPS_ConnectInstance($extraDevice, $gateways[1]);
IPS_SetProperty($extraDevice, 'MQTTBaseTopic', 'synthetic/third');
IPS_SetProperty($extraDevice, 'IEEE', '0x0000000000000002');
IPS_ApplyChanges($extraDevice);
$form = $fields(json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR)['elements']);
$check(array_column($form['DetectedBaseTopic']['options'], 'caption') === ['synthetic/second', 'synthetic/third']
    && IPS_GetProperty($id, 'BaseTopic') === 'synthetic/second' && !array_key_exists('value', $form['DetectedBaseTopic']), 'Multiple topics on a shared gateway never silently change the selected system');
IPS_SetProperty($id, 'BaseTopic', 'synthetic/manual');
IPS_ApplyChanges($id);
$form = $fields(json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR)['elements']);
$check($module->RefreshInterval() === 1000 && in_array([['name' => 'BaseTopic', 'value' => 'synthetic/manual']], array_column($form['DetectedBaseTopic']['options'], 'value'), true), 'An explicitly applied manual topic remains valid and can receive its automatic check even without a matching device catalog');
IPS_SetProperty($id, 'BaseTopic', 'synthetic/second');
IPS_ApplyChanges($id);
IPS_DisconnectInstance($id);
$module->MessageSink(time(), $id, FM_DISCONNECT, []);
$options = json_decode($module->updates['DetectedBaseTopic']['options'], true, 32, JSON_THROW_ON_ERROR);
$check(count($options) === 1 && $options[0]['value'] === [['name' => 'BaseTopic', 'value' => 'synthetic/second']], 'Disconnect removes stale suggestions but retains a valid current selection');
$check($module->Draft() === 'synthetic-unsaved-draft', 'Disconnected and reconfigured scopes retain the draft bytes');
$check(!str_contains(implode('', $module->debugMessages), 'synthetic-unsaved-draft'), 'Connection/topic diagnostics never include draft contents');
echo "Gateway topic selection and live form refresh passed.\n";
