<?php

use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\Store\FlockStore;

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor/autoload.php') && $vendor !== \dirname($vendor)) {
    $vendor = \dirname($vendor);
}
require $vendor.'/vendor/autoload.php';

ini_set('display_errors', 'stderr');

[, $lockPath, $resource] = $argv;

try {
    $store = new FlockStore($lockPath, true);
    $key = new Key($resource);

    echo "waiting\n";
    $store->waitAndSave($key);

    // Creating this file fails when another process holds the lock at the same time
    if (!$owner = @fopen($lockPath.'/owner', 'x')) {
        throw new RuntimeException('Another process holds the lock.');
    }
    echo "acquired\n";

    if ("release\n" !== $command = fgets(\STDIN)) {
        throw new RuntimeException(\sprintf('Unexpected command "%s".', $command));
    }

    fclose($owner);
    unlink($lockPath.'/owner');
    $store->delete($key);
    echo "released\n";

    // On Windows, exiting resets the socket, which discards the output the parent did not read yet, so wait for proc_close() to close the input
    fgets(\STDIN);
} catch (Throwable $e) {
    fwrite(\STDERR, $e."\n");
    exit(1);
}
