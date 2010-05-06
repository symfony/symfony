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
use Symfony\Components\Console\Application;

class ListCommandTest extends \PHPUnit_Framework_TestCase
{
    public function testExecute()
    {
        $application = new Application();

        $commandTester = new CommandTester($application->getCommand('list'));
        $commandTester->execute(array());
        $this->assertRegExp('/help   Displays help for a command/', $commandTester->getDisplay(), '->execute() returns a list of available commands');

        $commandTester->execute(array('--xml' => true));
        $this->assertRegExp('/<command id="list" namespace="_global" name="list">/', $commandTester->getDisplay(), '->execute() returns a list of available commands in XML if --xml is passed');
    }
}
