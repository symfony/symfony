<?php

if (4 > $_SERVER['argc']) {
    echo "Usage: commit-range branch dir1 dir2 ... dirN\n";
    exit(1);
}

$dirs = $_SERVER['argv'];
array_shift($dirs);
$range = array_shift($dirs);
$branch = array_shift($dirs);

$packages = array();
$flags = PHP_VERSION_ID >= 50400 ? JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE : 0;

foreach ($dirs as $dir) {
    if (!`git diff --name-only $range -- $dir`) {
        continue;
    }
    echo "$dir\n";

    $package = json_decode(file_get_contents($dir.'/composer.json'));

    $package->repositories = array(array(
        'type' => 'composer',
        'url' => 'file://'.__DIR__.'/',
    ));
    file_put_contents($dir.'/composer.json', json_encode($package, $flags));
    passthru("cd $dir && tar -cf package.tar --exclude='package.tar' *");

    $package->version = $branch.'.x-dev';
    $package->dist['type'] = 'tar';
    $package->dist['url'] = 'file://'.__DIR__."/$dir/package.tar";

    $packages[$package->name][$package->version] = $package;

    $versions = file_get_contents('https://packagist.org/packages/'.$package->name.'.json');
    $versions = json_decode($versions);

    foreach ($versions->package->versions as $version => $package) {
        $packages[$package->name] += array($version => $package);
    }
}

file_put_contents('packages.json', json_encode(compact('packages'), $flags));
