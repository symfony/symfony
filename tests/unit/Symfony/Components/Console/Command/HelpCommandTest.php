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
use Symfony\Components\Console\Command\HelpCommand;
use Symfony\Components\Console\Command\ListCommand;
use Symfony\Components\Console\Application;

$t = new LimeTest(4);

// ->execute()
$t->diag('->execute()');

$command = new HelpCommand();
$command->setCommand(new ListCommand());

$commandTester = new CommandTester($command);
$commandTester->execute(array());
$t->like($commandTester->getDisplay(), '/list \[--xml\] \[namespace\]/', '->execute() returns a text help for the given command');

$commandTester->execute(array('--xml' => true));
$t->like($commandTester->getDisplay(), '/<command/', '->execute() returns an XML help text if --xml is passed');

$application = new Application();
$commandTester = new CommandTester($application->getCommand('help'));
$commandTester->execute(array('command_name' => 'list'));
$t->like($commandTester->getDisplay(), '/list \[--xml\] \[namespace\]/', '->execute() returns a text help for the given command');

$commandTester->execute(array('command_name' => 'list', '--xml' => true));
$t->like($commandTester->getDisplay(), '/<command/', '->execute() returns an XML help text if --xml is passed');
