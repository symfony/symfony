<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Console\Messenger\RunCommandMessageHandler;

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = dirname($vendor);
}
require $vendor.'/vendor/autoload.php';

$handler = new RunCommandMessageHandler(new Application(), $argv[1]);
$handler(new RunCommandMessage($argv[2], true, false, true));
