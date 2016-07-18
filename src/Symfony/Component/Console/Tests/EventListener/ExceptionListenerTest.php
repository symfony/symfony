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

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleExceptionEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\EventListener\ExceptionListener;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Tests\Output\TestOutput;

class ExceptionListenerTest extends \PHPUnit_Framework_TestCase
{
    public function testOnKernelException()
    {
        $logger = $this->getLogger();
        $listener = new ExceptionListener($logger);

        $exception = new \RuntimeException('An error occurred');

        $logger
            ->expects($this->once())
            ->method('error')
            ->with('Exception thrown while running command: "{command}". Message: "{message}"', array('exception' => $exception, 'command' => '\'test:run\' --foo=baz buzz', 'message' => 'An error occurred'))
        ;

        $input = array(
            'name' => 'test:run',
            '--foo' => 'baz',
            'bar' => 'buzz'
        );

        $listener->onKernelException($this->getConsoleExceptionEvent($exception, $input, 1));
    }

    public function testOnKernelTerminateForNonZeroExitCodeWritesToLog()
    {
        $logger = $this->getLogger();
        $listener = new ExceptionListener($logger);

        $logger
            ->expects($this->once())
            ->method('error')
            ->with('Command "{command}" exited with status code "{code}"', array('command' => '\'test:run\'', 'code' => 255))
        ;

        $listener->onKernelTerminate($this->getConsoleTerminateEvent(array('name' => 'test:run'), 255));
    }

    public function testOnKernelTerminateForZeroExitCodeDoesNotWriteToLog()
    {
        $logger = $this->getLogger();
        $listener = new ExceptionListener($logger);

        $logger
            ->expects($this->never())
            ->method('error')
        ;

        $listener->onKernelTerminate($this->getConsoleTerminateEvent(array('name' => 'test:run'), 0));
    }

    public function testGetSubscribedEvents()
    {
        $this->assertEquals(
            array(
                'console.exception' => array('onKernelException', -128),
                'console.terminate' => array('onKernelTerminate', -128),
            ),
            ExceptionListener::getSubscribedEvents()
        );
    }

    private function getLogger()
    {
        return $this->getMockForAbstractClass(LoggerInterface::class);
    }

    private function getConsoleExceptionEvent(\Exception $exception, $input, $exitCode)
    {
        return new ConsoleExceptionEvent(new Command('test:run'), new ArrayInput($input), new TestOutput(), $exception, $exitCode);
    }

    private function getConsoleTerminateEvent($input, $exitCode)
    {
        return new ConsoleTerminateEvent(new Command('test:run'), new ArrayInput($input), new TestOutput(), $exitCode);
    }
}
