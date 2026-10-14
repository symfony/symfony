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

use Symfony\Component\Process\Process;

require is_file(\dirname(__DIR__).'/vendor/autoload.php') ? \dirname(__DIR__).'/vendor/autoload.php' : \dirname(__DIR__, 5).'/vendor/autoload.php';

$options = getopt('e:c:i:') + ['e' => 'php', 'i' => null];

foreach ((array) $options['c'] as $code) {
    $process = new Process([$options['e'], '-r', $code]);
    $process->inheritStdio();
    $process->setInput($options['i']);
    $process->run(static function () {
        echo 'callback called';
    });

    echo \PHP_EOL.'exit code: '.$process->getExitCode();
    echo \PHP_EOL.'captured output: '.json_encode($process->getOutput().$process->getErrorOutput());
}
