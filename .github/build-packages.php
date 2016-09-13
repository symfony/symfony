<?php

if (3 > $_SERVER['argc']) {
    echo "Usage: branch dir1 dir2 ... dirN\n";
    exit(1);
}
chdir(dirname(__DIR__));

$dirs = $_SERVER['argv'];
array_shift($dirs);
$mergeBase = trim(shell_exec(sprintf('git merge-base %s HEAD', array_shift($dirs))));

$packages = array();
$flags = PHP_VERSION_ID >= 50400 ? JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE : 0;

foreach ($dirs as $k => $dir) {
    if (!system("git diff --name-only $mergeBase -- $dir", $exitStatus)) {
        if ($exitStatus) {
            exit($exitStatus);
        }
        unset($dirs[$k]);
        continue;
    }
    echo "$dir\n";

    $json = ltrim(file_get_contents($dir.'/composer.json'));
    if (null === $package = json_decode($json)) {
        passthru("composer validate $dir/composer.json");
        exit(1);
    }

    $package->repositories = array(array(
        'type' => 'composer',
        'url' => 'file://'.str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__)).'/',
    ));
    if (false === strpos($json, "\n    \"repositories\": [\n")) {
        $json = rtrim(json_encode(array('repositories' => $package->repositories), $flags), "\n}").','.substr($json, 1);
        file_put_contents($dir.'/composer.json', $json);
    }
    passthru("cd $dir && tar -cf package.tar --exclude='package.tar' *");

    if (!isset($package->extra->{'branch-alias'}->{'dev-master'})) {
        echo "Missing \"dev-master\" branch-alias in composer.json extra.\n";
        exit(1);
    }
    $package->version = str_replace('-dev', '.999', $package->extra->{'branch-alias'}->{'dev-master'});
    $package->dist['type'] = 'tar';
    $package->dist['url'] = 'file://'.str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__))."/$dir/package.tar";

    $packages[$package->name][$package->version] = $package;

    $versions = file_get_contents('https://packagist.org/packages/'.$package->name.'.json');
    $versions = json_decode($versions);

    foreach ($versions->package->versions as $v => $package) {
        $packages[$package->name] += array($v => $package);
    }
}

file_put_contents('packages.json', json_encode(compact('packages'), $flags));

if ($dirs) {
    $json = ltrim(file_get_contents('composer.json'));
    if (null === $package = json_decode($json)) {
        passthru("composer validate $dir/composer.json");
        exit(1);
    }

    $package->repositories = array(array(
        'type' => 'composer',
        'url' => 'file://'.str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__)).'/',
    ));
    if (false === strpos($json, "\n    \"repositories\": [\n")) {
        $json = rtrim(json_encode(array('repositories' => $package->repositories), $flags), "\n}").','.substr($json, 1);
        file_put_contents('composer.json', $json);
    }
}
