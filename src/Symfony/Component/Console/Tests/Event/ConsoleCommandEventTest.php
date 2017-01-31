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
    /** @var Command */
    private $command;

    /** @var \PHPUnit_Framework_MockObject_MockObject|InputInterface */
    private $input;

    /** @var \PHPUnit_Framework_MockObject_MockObject|OutputInterface */
    private $output;

    public function setUp()
    {
        $this->command = new Command('my_mock_command');
        $this->input = $this->getMock(InputInterface::class);
        $this->output = $this->getMock(OutputInterface::class);
    }

    public function testDisableCommand()
    {
        $event = new ConsoleCommandEvent($this->command, $this->input, $this->output);
        $event->disableCommand();
        $this->assertFalse($event->commandShouldRun());
    }

    public function testEnableCommand()
    {
        $event = new ConsoleCommandEvent($this->command, $this->input, $this->output);
        $event->enableCommand();
        $this->assertTrue($event->commandShouldRun());
    }
}
