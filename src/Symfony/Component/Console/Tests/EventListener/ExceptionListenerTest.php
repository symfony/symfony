<?php

namespace Symfony\Component\Console\Tests\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleExceptionEvent;
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
            ->with('An error occurred', array('exception' => $exception))
        ;

        $listener->onKernelException($this->getConsoleExceptionEvent($exception, 0));
    }

    public function testOnKernelTerminate()
    {
        $this->markTestIncomplete();
    }

    private function getLogger()
    {
        return $this->getMockForAbstractClass(LoggerInterface::class);
    }

    private function getConsoleExceptionEvent(\Exception $exception, $exitCode)
    {
        return new ConsoleExceptionEvent(new Command('test'), new ArrayInput([]), new TestOutput(), $exception, $exitCode);
    }
}
