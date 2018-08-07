<?php

array_shift($_SERVER['argv']);
$dirs = $_SERVER['argv'];

function getRelevantContent(array $composerJson)
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

    return $relevantContent;
}

function getContentHash(array $composerJson)
{
    $relevantContent = getRelevantContent($composerJson);
    ksort($relevantContent);

    return md5(json_encode($relevantContent));
}

$composerJsons = array();

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
    $composerJsons[$composerJson['name']] = array($dir, $composerLock['packages'], getRelevantContent($composerJson));
}

foreach ($composerJsons as list($dir, $lockedPackages)) {
    foreach ($lockedPackages as $lockedJson) {
        if (0 !== strpos($version = $lockedJson['version'], 'dev-') && '-dev' !== substr($version, -4)) {
            continue;
        }

        if (!isset($composerJsons[$name = $lockedJson['name']])) {
            echo "$dir/composer.lock references missing $name.\n";
            @unlink($dir.'/composer.lock');
            continue 2;
        }

        foreach (array('minimum-stability', 'prefer-stable', 'repositories') as $key) {
            if (array_key_exists($key, $composerJsons[$name][2])) {
                $lockedJson[$key] = $composerJsons[$name][2][$key];
            }
        }

        // use weak comparison to ignore ordering
        if (getRelevantContent($lockedJson) != $composerJsons[$name][2]) {
            echo "$dir/composer.lock is not in sync with $name.\n";
            @unlink($dir.'/composer.lock');
            continue 2;
        }
    }
}
