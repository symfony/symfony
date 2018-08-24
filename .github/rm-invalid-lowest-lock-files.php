<?php

error_reporting(-1);
set_error_handler(function ($type, $message, $file, $line) {
    if (error_reporting()) {
        throw new \ErrorException($message, 0, $type, $file, $line);
    }
});
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
    $composerLock += array('packages' => array(), 'packages-dev' => array());
    $composerJsons[$composerJson['name']] = array($dir, $composerLock['packages'] + $composerLock['packages-dev'], getRelevantContent($composerJson));
}

$referencedCommits = array();

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

        if (isset($composerJsons[$name][2]['repositories']) && !isset($lockedJson[$key]['repositories'])) {
            // the locked package has been patched locally but the lock references a commit,
            // which means the referencing package itself is not modified
            continue;
        }

        foreach (array('minimum-stability', 'prefer-stable') as $key) {
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

        if ($lockedJson['dist']['reference']) {
            $referencedCommits[$name][$lockedJson['dist']['reference']][] = $dir;
        }
    }
}

if (!$referencedCommits || (isset($_SERVER['TRAVIS_PULL_REQUEST']) && 'false' !== $_SERVER['TRAVIS_PULL_REQUEST'])) {
    // cached commits cannot be stale for PRs
    return;
}

@mkdir($_SERVER['HOME'].'/.cache/composer/repo/https---repo.packagist.org', 0777, true);

$ch = null;
$mh = curl_multi_init();
$sh = curl_share_init();
curl_share_setopt($sh, CURLSHOPT_SHARE, CURL_LOCK_DATA_COOKIE);
curl_share_setopt($sh, CURLSHOPT_SHARE, CURL_LOCK_DATA_DNS);
curl_share_setopt($sh, CURLSHOPT_SHARE, CURL_LOCK_DATA_SSL_SESSION);
$chs = array();

foreach ($referencedCommits as $name => $dirsByCommit) {
    $chs[] = $ch = array(curl_init(), fopen($_SERVER['HOME'].'/.cache/composer/repo/https---repo.packagist.org/provider-'.strtr($name, '/', '$').'.json', 'wb'));
    curl_setopt($ch[0], CURLOPT_URL, 'https://repo.packagist.org/p/'.$name.'.json');
    curl_setopt($ch[0], CURLOPT_FILE, $ch[1]);
    curl_setopt($ch[0], CURLOPT_SHARE, $sh);
    curl_multi_add_handle($mh, $ch[0]);
}

do {
    curl_multi_exec($mh, $active);
    curl_multi_select($mh);
} while ($active);

foreach ($chs as list($ch, $fd)) {
    curl_multi_remove_handle($mh, $ch);
    curl_close($ch);
    fclose($fd);
}

foreach ($referencedCommits as $name => $dirsByCommit) {
    $repo = file_get_contents($_SERVER['HOME'].'/.cache/composer/repo/https---repo.packagist.org/provider-'.strtr($name, '/', '$').'.json');
    $repo = json_decode($repo, true);

    foreach ($repo['packages'][$name] as $version) {
        unset($referencedCommits[$name][$version['source']['reference']]);
    }
}

foreach ($referencedCommits as $name => $dirsByCommit) {
    foreach ($dirsByCommit as $dirs) {
        foreach ($dirs as $dir) {
            if (file_exists($dir.'/composer.lock')) {
                echo "$dir/composer.lock references old commit for $name.\n";
                @unlink($dir.'/composer.lock');
            }
        }
    }
}
