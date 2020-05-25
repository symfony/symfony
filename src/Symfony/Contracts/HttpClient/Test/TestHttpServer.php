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

class TestHttpServer
{
    private static $started;

    public static function start()
    {
        if (self::$started) {
            return;
        }

        $finder = new PhpExecutableFinder();
        $process = new Process(array_merge([$finder->find(false)], $finder->findArguments(), ['-dopcache.enable=0', '-dvariables_order=EGPCS', '-S', '127.0.0.1:8057']));
        $process->setWorkingDirectory(__DIR__.'/Fixtures/web');
        $process->start();

        register_shutdown_function([$process, 'stop']);
        sleep('\\' === \DIRECTORY_SEPARATOR ? 10 : 1);

        self::$started = true;
    }
}
