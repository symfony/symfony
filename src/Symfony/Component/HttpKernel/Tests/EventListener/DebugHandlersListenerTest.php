<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleEvent;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\EventListener\DebugHandlersListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class DebugHandlersListenerTest extends TestCase
{
    public function testConfigure()
    {
        $userHandler = function () {};
        $listener = new DebugHandlersListener($userHandler);
        $eHandler = new ErrorHandler();

        $exception = null;
        set_error_handler([$eHandler, 'handleError']);
        set_exception_handler([$eHandler, 'handleException']);
        try {
            $listener->configure();
        } catch (\Exception $exception) {
        } finally {
            restore_exception_handler();
            restore_error_handler();
        }

        if (null !== $exception) {
            throw $exception;
        }

        $this->assertSame($userHandler, $eHandler->setExceptionHandler('var_dump'));
    }

    public function testConfigureForHttpKernelWithNoTerminateWithException()
    {
        $listener = new DebugHandlersListener(null);
        $eHandler = new ErrorHandler();
        $event = new KernelEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create('/'),
            HttpKernelInterface::MAIN_REQUEST
        );

        $exception = null;
        $h = set_exception_handler([$eHandler, 'handleException']);
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
        $app = $this->createMock(Application::class);
        $app->expects($this->once())->method('getHelperSet')->willReturn(new HelperSet());
        $command = new Command(__FUNCTION__);
        $command->setApplication($app);
        $event = new ConsoleEvent($command, new ArgvInput(), new ConsoleOutput());

        $dispatcher->addSubscriber($listener);

        $xListeners = [
            KernelEvents::REQUEST => [[$listener, 'configure']],
            ConsoleEvents::COMMAND => [[$listener, 'configure']],
        ];
        $this->assertSame($xListeners, $dispatcher->getListeners());

        $exception = null;
        $eHandler = new ErrorHandler();
        set_error_handler([$eHandler, 'handleError']);
        set_exception_handler([$eHandler, 'handleException']);
        try {
            $dispatcher->dispatch($event, ConsoleEvents::COMMAND);
        } catch (\Exception $exception) {
        } finally {
            restore_exception_handler();
            restore_error_handler();
        }

        if (null !== $exception) {
            throw $exception;
        }

        $xHandler = $eHandler->setExceptionHandler('var_dump');
        $this->assertInstanceOf(\Closure::class, $xHandler);

        $app->expects($this->once())
            ->method(method_exists(Application::class, 'renderThrowable') ? 'renderThrowable' : 'renderException');

        $xHandler(new \Exception());
    }

    public function testReplaceExistingExceptionHandler()
    {
        $userHandler = function () {};
        $listener = new DebugHandlersListener($userHandler);
        $eHandler = new ErrorHandler();
        $eHandler->setExceptionHandler('var_dump');

        $exception = null;
        set_exception_handler([$eHandler, 'handleException']);
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
