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

use Symfony\Component\Process\PhpSubprocess;
use Symfony\Component\Process\Process;

require is_file(\dirname(__DIR__).'/vendor/autoload.php') ? \dirname(__DIR__).'/vendor/autoload.php' : \dirname(__DIR__, 5).'/vendor/autoload.php';

['e' => $php, 'p' => $process] = getopt('e:p:') + ['e' => 'php', 'p' => 'Process'];

if ('Process' === $process) {
    $p = new Process([$php, __DIR__.'/Fixtures/memory.php']);
} else {
    $p = new PhpSubprocess([__DIR__.'/Fixtures/memory.php'], null, null, 60, [$php]);
}

$p->mustRun();
echo $p->getOutput();
