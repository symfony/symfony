<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Process\Tests;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

require is_file(\dirname(__DIR__).'/vendor/autoload.php') ? \dirname(__DIR__).'/vendor/autoload.php' : \dirname(__DIR__, 5).'/vendor/autoload.php';

['e' => $php] = getopt('e:') + ['e' => 'php'];

try {
    $process = new Process([$php, '-r', "echo 'ready'; trigger_error('error', E_USER_ERROR);"]);
    $process->start();
    $process->setTimeout(0.5);
    while (!str_contains($process->getOutput(), 'ready')) {
        usleep(1000);
    }
    $process->isRunning() && $process->signal(\SIGSTOP);
    $process->wait();

    return $process->getExitCode();
} catch (ProcessTimedOutException $t) {
    echo $t->getMessage().\PHP_EOL;

    return 1;
}
