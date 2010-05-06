<?php

/*
 * This file is part of the Symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\Console\Command;

use Symfony\Components\Console\Tester\CommandTester;
use Symfony\Components\Console\Command\HelpCommand;
use Symfony\Components\Console\Command\ListCommand;
use Symfony\Components\Console\Application;

class HelpCommandTest extends \PHPUnit_Framework_TestCase
{
    public function testExecute()
    {
        $command = new HelpCommand();
        $command->setCommand(new ListCommand());

        $commandTester = new CommandTester($command);
        $commandTester->execute(array());
        $this->assertRegExp('/list \[--xml\] \[namespace\]/', $commandTester->getDisplay(), '->execute() returns a text help for the given command');

        $commandTester->execute(array('--xml' => true));
        $this->assertRegExp('/<command/', $commandTester->getDisplay(), '->execute() returns an XML help text if --xml is passed');

        $application = new Application();
        $commandTester = new CommandTester($application->getCommand('help'));
        $commandTester->execute(array('command_name' => 'list'));
        $this->assertRegExp('/list \[--xml\] \[namespace\]/', $commandTester->getDisplay(), '->execute() returns a text help for the given command');

        $commandTester->execute(array('command_name' => 'list', '--xml' => true));
        $this->assertRegExp('/<command/', $commandTester->getDisplay(), '->execute() returns an XML help text if --xml is passed');
    }
}
