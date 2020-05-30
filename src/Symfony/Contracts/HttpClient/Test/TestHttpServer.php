<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Contracts\HttpClient\Test;

use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * @experimental in 1.1
 */
class TestHttpServer
{
    private static $process;

    public static function start()
    {
        if (self::$process) {
            self::$process->stop();
        }

        $finder = new PhpExecutableFinder();
        $process = new Process(array_merge([$finder->find(false)], $finder->findArguments(), ['-dopcache.enable=0', '-dvariables_order=EGPCS', '-S', '127.0.0.1:8057']));
        $process->setWorkingDirectory(__DIR__.'/Fixtures/web');
        $process->start();

        do {
            usleep(50000);
        } while (!@fopen('http://127.0.0.1:8057/', 'r'));

        self::$process = $process;
    }
}
