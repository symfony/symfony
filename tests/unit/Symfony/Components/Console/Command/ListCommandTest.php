<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../../../bootstrap.php';

use Symfony\Components\Console\Tester\CommandTester;
use Symfony\Components\Console\Application;

$t = new LimeTest(2);

$application = new Application();

// ->execute()
$t->diag('->execute()');

$commandTester = new CommandTester($application->getCommand('list'));
$commandTester->execute(array());
$t->like($commandTester->getDisplay(), '/help   Displays help for a command/', '->execute() returns a list of available commands');

$commandTester->execute(array('--xml' => true));
$t->like($commandTester->getDisplay(), '/<command id="list" namespace="_global" name="list">/', '->execute() returns a list of available commands in XML if --xml is passed');
