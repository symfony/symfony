<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Event;

use Symfony\Component\Console\Event\ConsoleCommandEvent;

class ConsoleCommandEventTest extends \PHPUnit_Framework_TestCase
{
    public function testDisableEnableCommand()
    {
        $commandMock = $this->prophesize('Symfony\Component\Console\Command\Command');
        $inputMock = $this->prophesize('Symfony\Component\Console\Input\InputInterface');
        $outputMock = $this->prophesize('Symfony\Component\Console\Output\OutputInterface');
        $consoleCommandEvent = new ConsoleCommandEvent($commandMock->reveal(), $inputMock->reveal(), $outputMock->reveal());

        $this->assertTrue($consoleCommandEvent->commandShouldRun());

        $consoleCommandEvent->disableCommand();
        $this->assertFalse($consoleCommandEvent->commandShouldRun());

        $consoleCommandEvent->enableCommand();
        $this->assertTrue($consoleCommandEvent->commandShouldRun());
    }
}
