<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Console\Command;

use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Command\ListCommand;
use Symfony\Component\Console\Application;

class HelpCommandTest extends \PHPUnit_Framework_TestCase
{
    public function testExecute()
    {
        $command = new HelpCommand();

        $commandTester = new CommandTester($command);
        $command->setCommand(new ListCommand());
        $commandTester->execute(array());
        $this->assertRegExp('/list \[--xml\] \[namespace\]/', $commandTester->getDisplay(), '->execute() returns a text help for the given command');

        $command->setCommand(new ListCommand());
        $commandTester->execute(array('--xml' => true));
        $this->assertRegExp('/<command/', $commandTester->getDisplay(), '->execute() returns an XML help text if --xml is passed');

        $application = new Application();
        $commandTester = new CommandTester($application->get('help'));
        $commandTester->execute(array('command_name' => 'list'));
        $this->assertRegExp('/list \[--xml\] \[namespace\]/', $commandTester->getDisplay(), '->execute() returns a text help for the given command');

        $commandTester->execute(array('command_name' => 'list', '--xml' => true));
        $this->assertRegExp('/<command/', $commandTester->getDisplay(), '->execute() returns an XML help text if --xml is passed');
    }
}
