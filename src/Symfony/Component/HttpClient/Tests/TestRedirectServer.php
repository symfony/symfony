<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests;

use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Starts a server that redirects TLS requests to the plain URL of the same host and port.
 */
class TestRedirectServer
{
    private static $process;

    public static function start(): void
    {
        if (self::$process && self::$process->isRunning()) {
            return;
        }

        $finder = new PhpExecutableFinder();
        $process = new Process(array_merge([$finder->find(false)], $finder->findArguments(), [__DIR__.'/Fixtures/tls/redirect-server.php', '8059']));
        $process->start();
        self::$process = $process;
        register_shutdown_function([$process, 'stop']);

        do {
            usleep(50000);
        } while ($process->isRunning() && !@fsockopen('127.0.0.1', 8059));
    }
}
