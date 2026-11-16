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

class TestSocketServer
{
    private static array $processes = [];

    public static function start(string $script, int $port): void
    {
        $fixtures = realpath(__DIR__.'/Fixtures').\DIRECTORY_SEPARATOR;
        $path = realpath($fixtures.$script);

        if (!$path || !str_starts_with($path, $fixtures) || !is_file($path)) {
            throw new \InvalidArgumentException(\sprintf('Script "%s" is not a file in "%s".', $script, $fixtures));
        }

        if (isset(self::$processes[$port])) {
            [$running, $process] = self::$processes[$port];

            if ($path !== $running) {
                throw new \LogicException(\sprintf('Port %d already serves "%s".', $port, $running));
            }

            if ($process->isRunning()) {
                return;
            }
        }

        if ($socket = @fsockopen('127.0.0.1', $port)) {
            fclose($socket);

            throw new \RuntimeException(\sprintf('Port %d is already in use.', $port));
        }

        $finder = new PhpExecutableFinder();
        $process = new Process(array_merge([$finder->find(false)], $finder->findArguments(), [$path, (string) $port]));
        $process->start();
        self::$processes[$port] = [$path, $process];
        register_shutdown_function([$process, 'stop']);
        // A loaded CI runner, like the Windows one, can take several seconds to start a process
        $started = hrtime(true) / 1E9;
        $deadline = $started + 30;

        while (!$socket = @fsockopen('127.0.0.1', $port)) {
            if (!$process->isRunning() || $deadline < hrtime(true) / 1E9) {
                $elapsed = hrtime(true) / 1E9 - $started;
                $message = $process->isRunning()
                    ? \sprintf('Script "%s" did not listen on port %d after %.1fs.', $script, $port, $elapsed)
                    : \sprintf('Script "%s" exited with code %d after %.1fs without listening on port %d.', $script, $process->getExitCode(), $elapsed, $port);
                $process->stop(0);

                throw new \RuntimeException($message.rtrim("\n".$process->getOutput().$process->getErrorOutput()));
            }

            usleep(50000);
        }

        fclose($socket);
    }
}
