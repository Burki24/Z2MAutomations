<?php

declare(strict_types=1);

foreach (['validate_structure.php', 'module.php', 'mqtt.php'] as $test) {
    $process = proc_open([PHP_BINARY, __DIR__ . '/' . $test], [STDIN, STDOUT, STDERR], $pipes, dirname(__DIR__));
    if (!is_resource($process) || proc_close($process) !== 0) {
        exit(1);
    }
}

foreach ([['node', __DIR__ . '/build-extension.mjs', '--check'], ['node', '--test', __DIR__ . '/extension.test.mjs']] as $command) {
    $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, dirname(__DIR__));
    if (!is_resource($process) || proc_close($process) !== 0) {
        exit(1);
    }
}

echo "All offline checks passed.\n";
