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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConsoleCommandEventTest extends \PHPUnit_Framework_TestCase
{
    public function testDisableEnableCommand()
    {
        $commandMock = $this->prophesize(Command::class);
        $inputMock = $this->prophesize(InputInterface::class);
        $outputMock = $this->prophesize(OutputInterface::class);
        $consoleCommandEvent = new ConsoleCommandEvent($commandMock->reveal(), $inputMock->reveal(), $outputMock->reveal());

        $this->assertTrue($consoleCommandEvent->commandShouldRun());

        $consoleCommandEvent->disableCommand();
        $this->assertFalse($consoleCommandEvent->commandShouldRun());

        $consoleCommandEvent->enableCommand();
        $this->assertTrue($consoleCommandEvent->commandShouldRun());
    }
}
