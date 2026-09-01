<?php

declare(strict_types=1);

require '/app/vendor/autoload.php';

$list = $argv[1] ?? '';

if ($list === '' || !is_file($list)) {
    fwrite(STDERR, "Provider class list is missing.\n");
    exit(1);
}

$failed = false;

foreach (file($list, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $class) {
    if (!class_exists($class)) {
        fwrite(STDERR, "Missing provider class: {$class}\n");
        $failed = true;
        continue;
    }

    fwrite(STDOUT, "Provider class OK: {$class}\n");
}

exit($failed ? 1 : 0);
