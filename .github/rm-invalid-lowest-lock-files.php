<?php

array_shift($_SERVER['argv']);
$dirs = $_SERVER['argv'];

function getContentHash($composerJson)
{
    $relevantKeys = array(
        'name',
        'require',
        'require-dev',
        'conflict',
        'replace',
        'provide',
        'minimum-stability',
        'prefer-stable',
        'repositories',
        'extra',
    );

    $relevantContent = array();

    foreach (array_intersect($relevantKeys, array_keys($composerJson)) as $key) {
        $relevantContent[$key] = $composerJson[$key];
    }
    if (isset($composerJson['config']['platform'])) {
        $relevantContent['config']['platform'] = $composerJson['config']['platform'];
    }

    ksort($relevantContent);

    return md5(json_encode($relevantContent));
}

$composerLocks = array();

foreach ($dirs as $dir) {
    if (!file_exists($dir.'/composer.lock') || !$composerLock = @json_decode(file_get_contents($dir.'/composer.lock'), true)) {
        echo "$dir/composer.lock not found or invalid.\n";
        @unlink($dir.'/composer.lock');
        continue;
    }
    if (!file_exists($dir.'/composer.json') || !$composerJson = @json_decode(file_get_contents($dir.'/composer.json'), true)) {
        echo "$dir/composer.json not found or invalid.\n";
        @unlink($dir.'/composer.lock');
        continue;
    }
    if (!isset($composerLock['content-hash']) || getContentHash($composerJson) !== $composerLock['content-hash']) {
        echo "$dir/composer.lock is outdated.\n";
        @unlink($dir.'/composer.lock');
        continue;
    }
    $composerLocks[$composerJson['name']] = array($dir, $composerLock, $composerJson);
}

foreach ($composerLocks as list($dir, $composerLock)) {
    foreach ($composerLock['packages'] as $composerJson) {
        if (0 !== strpos($version = $composerJson['version'], 'dev-') && '-dev' !== substr($version, -4)) {
            continue;
        }

        if (!isset($composerLocks[$name = $composerJson['name']])) {
            echo "$dir/composer.lock references missing $name.\n";
            @unlink($dir.'/composer.lock');
            continue 2;
        }

        foreach (array('minimum-stability', 'prefer-stable', 'repositories') as $key) {
            if (array_key_exists($key, $composerLocks[$name][2])) {
                $composerJson[$key] = $composerLocks[$name][2][$key];
            }
        }

        if (getContentHash($composerJson) !== $composerLocks[$name][1]['content-hash']) {
            echo "$dir/composer.lock is not in sync with $name.\n";
            @unlink($dir.'/composer.lock');
            continue 2;
        }
    }
}
