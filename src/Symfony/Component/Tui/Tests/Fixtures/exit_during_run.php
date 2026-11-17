<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Revolt\EventLoop;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\TextWidget;

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = dirname($vendor);
}
require $vendor.'/vendor/autoload.php';

$tui = new Tui();
$tui->add(new TextWidget('Hello'));

EventLoop::delay(0.05, static function () use ($argv): void {
    if ('fork' === ($argv[1] ?? null)) {
        echo '[fork]';
        if (0 === $pid = pcntl_fork()) {
            exit(0);
        }
        pcntl_waitpid($pid, $status);
        echo '[child exited]';
    }

    echo '[exit]';
    exit(0);
});

$tui->run();

echo '[run returned]';
