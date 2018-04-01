<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\HttpKernel\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symphony\Component\Console\Event\ConsoleEvent;
use Symphony\Component\Console\Command\Command;
use Symphony\Component\Console\ConsoleEvents;
use Symphony\Component\Console\Helper\HelperSet;
use Symphony\Component\Console\Input\ArgvInput;
use Symphony\Component\Console\Output\ConsoleOutput;
use Symphony\Component\Debug\ErrorHandler;
use Symphony\Component\Debug\ExceptionHandler;
use Symphony\Component\EventDispatcher\EventDispatcher;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpKernel\Event\KernelEvent;
use Symphony\Component\HttpKernel\EventListener\DebugHandlersListener;
use Symphony\Component\HttpKernel\HttpKernelInterface;
use Symphony\Component\HttpKernel\KernelEvents;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class DebugHandlersListenerTest extends TestCase
{
    public function testConfigure()
    {
        $logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();
        $userHandler = function () {};
        $listener = new DebugHandlersListener($userHandler, $logger);
        $xHandler = new ExceptionHandler();
        $eHandler = new ErrorHandler();
        $eHandler->setExceptionHandler(array($xHandler, 'handle'));

        $exception = null;
        set_error_handler(array($eHandler, 'handleError'));
        set_exception_handler(array($eHandler, 'handleException'));
        try {
            $listener->configure();
        } catch (\Exception $exception) {
        }
        restore_exception_handler();
        restore_error_handler();

        if (null !== $exception) {
            throw $exception;
        }

        $this->assertSame($userHandler, $xHandler->setHandler('var_dump'));

        $loggers = $eHandler->setLoggers(array());

        $this->assertArrayHasKey(E_DEPRECATED, $loggers);
        $this->assertSame(array($logger, LogLevel::INFO), $loggers[E_DEPRECATED]);
    }

    public function testConfigureForHttpKernelWithNoTerminateWithException()
    {
        $listener = new DebugHandlersListener(null);
        $eHandler = new ErrorHandler();
        $event = new KernelEvent(
            $this->getMockBuilder('Symphony\Component\HttpKernel\HttpKernelInterface')->getMock(),
            Request::create('/'),
            HttpKernelInterface::MASTER_REQUEST
        );

        $exception = null;
        $h = set_exception_handler(array($eHandler, 'handleException'));
        try {
            $listener->configure($event);
        } catch (\Exception $exception) {
        }
        restore_exception_handler();

        if (null !== $exception) {
            throw $exception;
        }

        $this->assertNull($h);
    }

    public function testConsoleEvent()
    {
        $dispatcher = new EventDispatcher();
        $listener = new DebugHandlersListener(null);
        $app = $this->getMockBuilder('Symphony\Component\Console\Application')->getMock();
        $app->expects($this->once())->method('getHelperSet')->will($this->returnValue(new HelperSet()));
        $command = new Command(__FUNCTION__);
        $command->setApplication($app);
        $event = new ConsoleEvent($command, new ArgvInput(), new ConsoleOutput());

        $dispatcher->addSubscriber($listener);

        $xListeners = array(
            KernelEvents::REQUEST => array(array($listener, 'configure')),
            ConsoleEvents::COMMAND => array(array($listener, 'configure')),
        );
        $this->assertSame($xListeners, $dispatcher->getListeners());

        $exception = null;
        $eHandler = new ErrorHandler();
        set_error_handler(array($eHandler, 'handleError'));
        set_exception_handler(array($eHandler, 'handleException'));
        try {
            $dispatcher->dispatch(ConsoleEvents::COMMAND, $event);
        } catch (\Exception $exception) {
        }
        restore_exception_handler();
        restore_error_handler();

        if (null !== $exception) {
            throw $exception;
        }

        $xHandler = $eHandler->setExceptionHandler('var_dump');
        $this->assertInstanceOf('Closure', $xHandler);

        $app->expects($this->once())
            ->method('renderException');

        $xHandler(new \Exception());
    }

    public function testReplaceExistingExceptionHandler()
    {
        $userHandler = function () {};
        $listener = new DebugHandlersListener($userHandler);
        $eHandler = new ErrorHandler();
        $eHandler->setExceptionHandler('var_dump');

        $exception = null;
        set_exception_handler(array($eHandler, 'handleException'));
        try {
            $listener->configure();
        } catch (\Exception $exception) {
        }
        restore_exception_handler();

        if (null !== $exception) {
            throw $exception;
        }

        $this->assertSame($userHandler, $eHandler->setExceptionHandler('var_dump'));
    }
}
