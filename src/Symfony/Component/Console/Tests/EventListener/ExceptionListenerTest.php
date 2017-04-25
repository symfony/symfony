<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\EventListener\ExceptionListener;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExceptionListenerTest extends TestCase
{
    public function testOnConsoleError()
    {
        $exception = new \RuntimeException('An error occurred');

        $logger = $this->getLogger();
        $logger
            ->expects($this->once())
            ->method('error')
            ->with('Error thrown while running command "{command}". Message: "{message}"', array('error' => $exception, 'command' => 'test:run --foo=baz buzz', 'message' => 'An error occurred'))
        ;

        $listener = new ExceptionListener($logger);
        $listener->onConsoleError($this->getConsoleErrorEvent($exception, new ArgvInput(array('console.php', 'test:run', '--foo=baz', 'buzz')), 1, new Command('test:run')));
    }

    public function testOnConsoleErrorWithNoCommandAndNoInputString()
    {
        $exception = new \RuntimeException('An error occurred');

        $logger = $this->getLogger();
        $logger
            ->expects($this->once())
            ->method('error')
            ->with('An error occurred while using the console. Message: "{message}"', array('error' => $exception, 'message' => 'An error occurred'))
        ;

        $listener = new ExceptionListener($logger);
        $listener->onConsoleError($this->getConsoleErrorEvent($exception, new NonStringInput(), 1));
    }

    public function testOnConsoleErrorWithoutCommand()
    {
        $exception = new \RuntimeException('An error occurred');

        $logger = $this->getLogger();
        $logger
            ->expects($this->once())
            ->method('error')
            ->with('Error thrown while running command "{command}". Message: "{message}"', array('error' => $exception, 'command' => '', 'message' => 'An error occurred'))
        ;

        $listener = new ExceptionListener($logger);

        $event = new ConsoleErrorEvent(new ArgvInput(array('console.php', 'test:run', '--foo=baz', 'buzz')), $this->getOutput(), $exception, 1);
        $listener->onConsoleError($event);
    }

    public function testOnConsoleTerminateForNonZeroExitCodeWritesToLog()
    {
        $logger = $this->getLogger();
        $logger
            ->expects($this->once())
            ->method('error')
            ->with('Command "{command}" exited with code "{code}"', array('command' => 'test:run', 'code' => 255))
        ;

        $listener = new ExceptionListener($logger);
        $listener->onConsoleTerminate($this->getConsoleTerminateEvent(new ArgvInput(array('console.php', 'test:run')), 255));
    }

    public function testOnConsoleTerminateForZeroExitCodeDoesNotWriteToLog()
    {
        $logger = $this->getLogger();
        $logger
            ->expects($this->never())
            ->method('error')
        ;

        $listener = new ExceptionListener($logger);
        $listener->onConsoleTerminate($this->getConsoleTerminateEvent(new ArgvInput(array('console.php', 'test:run')), 0));
    }

    public function testGetSubscribedEvents()
    {
        $this->assertEquals(
            array(
                'console.error' => array('onConsoleError', -128),
                'console.terminate' => array('onConsoleTerminate', -128),
            ),
            ExceptionListener::getSubscribedEvents()
        );
    }

    public function testAllKindsOfInputCanBeLogged()
    {
        $logger = $this->getLogger();
        $logger
            ->expects($this->exactly(3))
            ->method('error')
            ->with('Command "{command}" exited with code "{code}"', array('command' => 'test:run --foo=bar', 'code' => 255))
        ;

        $listener = new ExceptionListener($logger);
        $listener->onConsoleTerminate($this->getConsoleTerminateEvent(new ArgvInput(array('console.php', 'test:run', '--foo=bar')), 255));
        $listener->onConsoleTerminate($this->getConsoleTerminateEvent(new ArrayInput(array('name' => 'test:run', '--foo' => 'bar')), 255));
        $listener->onConsoleTerminate($this->getConsoleTerminateEvent(new StringInput('test:run --foo=bar'), 255));
    }

    public function testCommandNameIsDisplayedForNonStringableInput()
    {
        $logger = $this->getLogger();
        $logger
            ->expects($this->once())
            ->method('error')
            ->with('Command "{command}" exited with code "{code}"', array('command' => 'test:run', 'code' => 255))
        ;

        $listener = new ExceptionListener($logger);
        $listener->onConsoleTerminate($this->getConsoleTerminateEvent($this->getMockBuilder(InputInterface::class)->getMock(), 255));
    }

    private function getLogger()
    {
        return $this->getMockForAbstractClass(LoggerInterface::class);
    }

    private function getConsoleErrorEvent(\Exception $exception, InputInterface $input, $exitCode, Command $command = null)
    {
        return new ConsoleErrorEvent($input, $this->getOutput(), $exception, $exitCode, $command);
    }

    private function getConsoleTerminateEvent(InputInterface $input, $exitCode)
    {
        return new ConsoleTerminateEvent(new Command('test:run'), $input, $this->getOutput(), $exitCode);
    }

    private function getOutput()
    {
        return $this->getMockBuilder(OutputInterface::class)->getMock();
    }
}

class NonStringInput extends Input
{
    public function getFirstArgument()
    {
    }

    public function hasParameterOption($values, $onlyParams = false)
    {
    }

    public function getParameterOption($values, $default = false, $onlyParams = false)
    {
    }

    public function parse()
    {
    }
}
