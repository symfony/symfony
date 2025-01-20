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
use Symfony\Component\Console\EventListener\ErrorListener;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;

class ErrorListenerTest extends TestCase
{
    public function testOnConsoleError()
    {
        $error = new \TypeError('An error occurred');

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('critical')
            ->with('Error thrown while running command "{command}". Message: "{message}"', ['exception' => $error, 'command' => 'test:run --foo=baz buzz', 'message' => 'An error occurred'])
        ;

        $listener = new ErrorListener($logger);
        $listener->onConsoleError(new ConsoleErrorEvent(new ArgvInput(['console.php', 'test:run', '--foo=baz', 'buzz']), $this->createMock(OutputInterface::class), $error, new Command('test:run')));
    }

    public function testOnConsoleErrorWithNoCommandAndNoInputString()
    {
        $error = new \RuntimeException('An error occurred');

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('critical')
            ->with('An error occurred while using the console. Message: "{message}"', ['exception' => $error, 'message' => 'An error occurred'])
        ;

        $listener = new ErrorListener($logger);
        $listener->onConsoleError(new ConsoleErrorEvent(new NonStringInput(), $this->createMock(OutputInterface::class), $error));
    }

    public function testOnConsoleTerminateForNonZeroExitCodeWritesToLog()
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('debug')
            ->with('Command "{command}" exited with code "{code}"', ['command' => 'test:run', 'code' => 255])
        ;

        $listener = new ErrorListener($logger);
        $listener->onConsoleTerminate($this->getConsoleTerminateEvent(new ArgvInput(['console.php', 'test:run']), 255));
    }

    public function testOnConsoleTerminateForZeroExitCodeDoesNotWriteToLog()
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->never())
            ->method('debug')
        ;

        $listener = new ErrorListener($logger);
        $listener->onConsoleTerminate($this->getConsoleTerminateEvent(new ArgvInput(['console.php', 'test:run']), 0));
    }

    public function testGetSubscribedEvents()
    {
        $this->assertSame(
            [
                'console.error' => ['onConsoleError', -128],
                'console.terminate' => ['onConsoleTerminate', -128],
            ],
            ErrorListener::getSubscribedEvents()
        );
    }

    public function testAllKindsOfInputCanBeLogged()
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->exactly(3))
            ->method('debug')
            ->with('Command "{command}" exited with code "{code}"', ['command' => 'test:run --foo=bar', 'code' => 255])
        ;

        $listener = new ErrorListener($logger);
        $listener->onConsoleTerminate($this->getConsoleTerminateEvent(new ArgvInput(['console.php', 'test:run', '--foo=bar']), 255));
        $listener->onConsoleTerminate($this->getConsoleTerminateEvent(new ArrayInput(['name' => 'test:run', '--foo' => 'bar']), 255));
        $listener->onConsoleTerminate($this->getConsoleTerminateEvent(new StringInput('test:run --foo=bar'), 255));
    }

    private function getConsoleTerminateEvent(InputInterface $input, $exitCode)
    {
        return new ConsoleTerminateEvent(new Command('test:run'), $input, $this->createMock(OutputInterface::class), $exitCode);
    }
}

class NonStringInput extends Input
{
    public function getFirstArgument(): ?string
    {
    }

    public function hasParameterOption($values, $onlyParams = false): bool
    {
    }

    public function getParameterOption($values, $default = false, $onlyParams = false): mixed
    {
    }

    public function parse(): void
    {
    }

    public function __toString(): string
    {
        return '';
    }
}
