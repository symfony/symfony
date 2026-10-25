<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Process\PhpProcess;

require is_file(\dirname(__DIR__, 2).'/vendor/autoload.php') ? \dirname(__DIR__, 2).'/vendor/autoload.php' : \dirname(__DIR__, 6).'/vendor/autoload.php';

$process = new PhpProcess('<?php echo "OK";');

if (0 === pcntl_fork()) {
    return;
}

pcntl_wait($status);
$process->run();

echo $process->getOutput();
