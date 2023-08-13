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
    private static array $process = [];

    /**
     * @param string|null $workingDirectory
     */
    public static function start(int $port = 8057/* , string $workingDirectory = null */): Process
    {
        $workingDirectory = \func_get_args()[1] ?? __DIR__.'/Fixtures/web';

        if (isset(self::$process[$port])) {
            self::$process[$port]->stop();
        } else {
            register_shutdown_function(static function () use ($port) {
                self::$process[$port]->stop();
            });
        }

        $finder = new PhpExecutableFinder();
        $process = new Process(array_merge([$finder->find(false)], $finder->findArguments(), ['-dopcache.enable=0', '-dvariables_order=EGPCS', '-S', '127.0.0.1:'.$port]));
        $process->setWorkingDirectory($workingDirectory);
        $process->start();
        self::$process[$port] = $process;

        do {
            usleep(50000);
        } while (!@fopen('http://127.0.0.1:'.$port, 'r'));

        return $process;
    }
}
