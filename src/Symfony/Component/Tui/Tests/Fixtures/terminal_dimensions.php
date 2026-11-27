<?php

use Symfony\Component\Tui\Terminal\Terminal;

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor/autoload.php')) {
    if ($vendor === \dirname($vendor)) {
        throw new RuntimeException('Cannot find the TUI test autoloader.');
    }
    $vendor = \dirname($vendor);
}
require $vendor.'/vendor/autoload.php';

// The parent sizes the pseudo terminal before releasing the child.
fgets(fopen('php://fd/3', 'r'));
putenv('COLUMNS');
putenv('LINES');

$terminal = new Terminal();
echo json_encode([$terminal->getColumns(), $terminal->getRows()]);
