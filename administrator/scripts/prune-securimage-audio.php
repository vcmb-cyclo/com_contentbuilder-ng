<?php

declare(strict_types=1);

$audioPath = dirname(__DIR__) . '/vendor/bgli100/securimage/audio';

if (!is_dir($audioPath)) {
    exit(0);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($audioPath, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);

foreach ($iterator as $item) {
    if ($item->isDir()) {
        rmdir($item->getPathname());
        continue;
    }

    unlink($item->getPathname());
}

rmdir($audioPath);
