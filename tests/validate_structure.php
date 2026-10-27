<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$readJson = static fn (string $path): array => json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
$check = static function (bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

foreach (['AGENTS.md', 'PROJECT_CONTEXT.md', 'README.md', 'CHANGELOG.md', 'LICENSE', '.gitattributes', '.editorconfig', '.gitmodules', 'docs/ARCHITECTURE.md', 'docs/DEVELOPMENT.md', 'docs/adr/0001-execute-automations-in-z2m.md', 'libs/z2m/README.md', '.github/workflows/style.yml', '.github/workflows/tests.yml', '.style/.php-cs-fixer.php', 'tests/stubs/autoload.php'] as $file) {
    $check(is_file($root . '/' . $file), 'Missing project file: ' . $file);
}

$library = $readJson($root . '/library.json');
$check($library['name'] === 'Z2MAutomations' && $library['compatibility']['version'] === '9.0', 'Library identity and platform');
$check(str_contains(file_get_contents($root . '/LICENSE'), 'PolyForm Noncommercial License 1.0.0'), 'Project license');
$ids = [$library['id']];
$modules = glob($root . '/*/module.json');
$check(count($modules) === 1, 'One scaffold module expected');
foreach ($modules as $path) {
    $module = $readJson($path);
    $check(!in_array($module['id'], $ids, true), 'Duplicate GUID');
    $ids[] = $module['id'];
    $check($module['prefix'] === 'Z2MA' && $module['type'] === 3, 'Module identity and type');
    $check($module['parentRequirements'] === ['{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}'] && $module['childRequirements'] === []
        && $module['implemented'] === ['{7F7632D9-FA40-4F38-8DEA-C83CD4325A32}'], 'Official MQTT data flow');
    $folder = dirname($path);
    foreach (['module.php', 'form.json', 'locale.json', 'README.md'] as $file) {
        $check(is_file($folder . '/' . $file), 'Missing module file: ' . $file);
    }
    $check(str_contains(file_get_contents($folder . '/module.php'), 'class Z2MAutomations extends IPSModuleStrict'), 'Strict base class');
    $form = $readJson($folder . '/form.json');
    $translations = $readJson($folder . '/locale.json')['translations']['de'];
    foreach (array_merge($form['elements'], $form['actions'], $form['status']) as $field) {
        $check(isset($translations[$field['caption']]), 'German form translation: ' . $field['caption']);
    }
}

$subscription = $readJson($root . '/.helper-sync.json');
$manifest = $readJson($root . '/libs/helper/manifest.json');
foreach ($subscription['helpers'] as $name => $helper) {
    $entry = $manifest['helpers'][$name];
    $check($entry['path'] === $helper['target'], 'Helper target');
    $check(hash_file('sha256', $root . '/' . $entry['path']) === $entry['sha256'], 'Helper integrity: ' . $name);
}
foreach ($ids as $id) {
    $check(preg_match('/\A\{[A-F0-9]{8}(?:-[A-F0-9]{4}){3}-[A-F0-9]{12}\}\z/D', $id) === 1, 'Invalid GUID');
}

echo "Project structure passed.\n";
