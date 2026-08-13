<?php
// Standalone JSON linter for harness documents. No Laravel bootstrap, so it
// stays usable from hooks and CI before composer install has run.
$paths = array_slice($argv, 1);
if (!$paths) {
    $paths = glob(__DIR__ . '/../schema/*.json') ?: [];
    $paths = array_merge($paths, glob(__DIR__ . '/../*.json') ?: []);
}

$failed = 0;
foreach ($paths as $path) {
    $raw = @file_get_contents($path);
    if ($raw === false) {
        fwrite(STDERR, "MISSING  $path\n");
        $failed++;
        continue;
    }
    try {
        json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        echo "OK       $path\n";
    } catch (JsonException $e) {
        fwrite(STDERR, "INVALID  $path — {$e->getMessage()}\n");
        $failed++;
    }
}

exit($failed === 0 ? 0 : 1);
