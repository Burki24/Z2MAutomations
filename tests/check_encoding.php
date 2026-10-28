<?php

declare(strict_types=1);

$ciRoot = getenv('SYMCON_MODULE_CI_ROOT') ?: dirname(__DIR__, 2) . '/Symcon_ModuleCI';
$checker = $ciRoot . '/php-tests/scripts/check-encoding.php';
if (!is_file($checker)) {
    fwrite(STDERR, "Symcon_ModuleCI encoding checker not found. Provide the sibling repository or set SYMCON_MODULE_CI_ROOT.\n");
    exit(1);
}
require_once $checker;
$errors = ProjectEncodingCheck::Scan(dirname(__DIR__));
foreach ($errors as $error) {
    fwrite(STDERR, $error . PHP_EOL);
}
if ($errors !== []) {
    exit(1);
}
echo "Shared project text encoding passed.\n";
