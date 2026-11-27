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
use Symfony\Component\ErrorHandler\BufferingLogger;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Debug\ErrorHandlerConfigurator;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\EventListener\DebugHandlersListener;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class DebugHandlersListenerTest extends TestCase
{
    public function testKernelExceptionsAreLoggedWithoutLegacyControls()
    {
        if (!method_exists(ErrorHandler::class, 'configure')) {
            $this->markTestSkipped('The error handler does not support configuring the default loggers.');
        }

        $logger = new BufferingLogger();
        $handler = new class($logger) extends ErrorHandler {
            public function throwAt(int $levels, bool $replace = false): int
            {
                throw new \LogicException('Legacy error configuration was called.');
            }
        };
        $exception = new \RuntimeException('Failed');
        $request = Request::create('/');
        $kernel = $this->createMock(HttpKernel::class);
        $kernel->expects($this->once())->method('terminateWithException')->with($exception, $request);
        $listener = new DebugHandlersListener();

        set_exception_handler([$handler, 'handleException']);
        try {
            $listener->configure(new KernelEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));
        } finally {
            restore_exception_handler();
        }

        $exitCode = new \ReflectionProperty(ErrorHandler::class, 'exitCode');
        $previousExitCode = $exitCode->getValue();
        try {
            $handler->handleException($exception);
        } finally {
            $exitCode->setValue(null, $previousExitCode);
        }

        $this->assertSame([], $logger->cleanLogs());
    }

    public function testConfigure()
    {
        $userHandler = static fn () => null;
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
            $this->createStub(HttpKernelInterface::class),
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

    public function testUncaughtExceptionLoggersAreDisabledOncePerErrorHandler()
    {
        $listener = new DebugHandlersListener(null);
        $event = new KernelEvent($this->createStub(HttpKernel::class), Request::create('/'), HttpKernelInterface::MAIN_REQUEST);
        $logger = new BufferingLogger();
        $otherLogger = new BufferingLogger();
        $eHandler = new ErrorHandler();
        (new ErrorHandlerConfigurator($logger, \E_ALL, 0, false, false))->configure($eHandler);
        $otherHandler = new ErrorHandler();
        (new ErrorHandlerConfigurator($otherLogger, \E_ALL, 0, false, false))->configure($otherHandler);

        set_exception_handler([$eHandler, 'handleException']);
        try {
            $listener->configure($event);
            $this->assertFalse($eHandler->handleError(\E_ERROR, 'First request', __FILE__, __LINE__));

            (new ErrorHandlerConfigurator($logger, \E_ALL, 0, false, false))->configure($eHandler);
            $listener->configure($event);
            $this->assertTrue($eHandler->handleError(\E_ERROR, 'Second request', __FILE__, __LINE__));
        } finally {
            restore_exception_handler();
        }

        $this->assertSame('Error: Second request', $logger->cleanLogs()[0][1] ?? null);

        set_exception_handler([$otherHandler, 'handleException']);
        try {
            $listener->configure($event);
        } finally {
            restore_exception_handler();
        }

        $this->assertFalse($otherHandler->handleError(\E_ERROR, 'Other handler', __FILE__, __LINE__));
        $this->assertSame([], $otherLogger->cleanLogs());
    }

    public function testResetRestoresTheExceptionHandlerReplacedForTheMainRequest()
    {
        $listener = new DebugHandlersListener(null);
        $kernel = $this->createStub(HttpKernel::class);
        $request = Request::create('/');
        $requestRef = \WeakReference::create($request);
        $eHandler = new ErrorHandler();
        $previous = static fn () => null;
        $eHandler->setExceptionHandler($previous);
        $getExceptionHandler = static function () use ($eHandler) {
            $handler = $eHandler->setExceptionHandler(null);
            $eHandler->setExceptionHandler($handler);

            return $handler;
        };

        set_exception_handler([$eHandler, 'handleException']);
        try {
            $listener->configure(new KernelEvent($kernel, Request::create('/'), HttpKernelInterface::MAIN_REQUEST));
            $listener->configure(new KernelEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));
        } finally {
            restore_exception_handler();
        }
        unset($request);

        $this->assertNotSame($previous, $getExceptionHandler());
        $this->assertNotNull($requestRef->get());

        $listener->reset();

        $this->assertSame($previous, $getExceptionHandler());
        $this->assertNull($requestRef->get());
    }

    public function testResetKeepsAnExceptionHandlerSetAfterConfiguring()
    {
        $listener = new DebugHandlersListener(null);
        $eHandler = new ErrorHandler();

        set_exception_handler([$eHandler, 'handleException']);
        try {
            $listener->configure(new KernelEvent($this->createStub(HttpKernel::class), Request::create('/'), HttpKernelInterface::MAIN_REQUEST));
        } finally {
            restore_exception_handler();
        }

        $userHandler = static fn () => null;
        $eHandler->setExceptionHandler($userHandler);
        $listener->reset();

        $this->assertSame($userHandler, $eHandler->setExceptionHandler(null));
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
            ->method('renderThrowable');

        $xHandler(new \Exception());
    }

    public function testReplaceExistingExceptionHandler()
    {
        $userHandler = static function () {};
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

    public function testConsoleEventIsIgnoredInWebMode()
    {
        $listener = new DebugHandlersListener(null, true);

        $eHandler = $this->configureWithErrorHandler($listener, $this->createConsoleEvent());

        $this->assertNull($eHandler->setExceptionHandler('var_dump'));
    }

    public function testWebModeClosureIsOnlyCalledForConsoleEvents()
    {
        $calls = 0;
        $listener = new DebugHandlersListener(null, static function () use (&$calls) {
            ++$calls;

            return true;
        });

        $this->configureWithErrorHandler($listener, new KernelEvent($this->createStub(HttpKernelInterface::class), Request::create('/'), HttpKernelInterface::MAIN_REQUEST));
        $this->assertSame(0, $calls);

        $eHandler = $this->configureWithErrorHandler($listener, $this->createConsoleEvent());
        $this->assertSame(1, $calls);
        $this->assertNull($eHandler->setExceptionHandler('var_dump'));
    }

    public function testConsoleEventIsHandledWhenWebModeClosureReturnsFalse()
    {
        $listener = new DebugHandlersListener(null, static fn () => false);

        $eHandler = $this->configureWithErrorHandler($listener, $this->createConsoleEvent());

        $this->assertInstanceOf(\Closure::class, $eHandler->setExceptionHandler('var_dump'));
    }

    private function createConsoleEvent(): ConsoleEvent
    {
        $app = $this->createStub(Application::class);
        $app->method('getHelperSet')->willReturn(new HelperSet());
        $command = new Command('test');
        $command->setApplication($app);

        return new ConsoleEvent($command, new ArgvInput(), new ConsoleOutput());
    }

    private function configureWithErrorHandler(DebugHandlersListener $listener, object $event): ErrorHandler
    {
        $eHandler = new ErrorHandler();
        set_error_handler([$eHandler, 'handleError']);
        set_exception_handler([$eHandler, 'handleException']);
        try {
            $listener->configure($event);
        } finally {
            restore_exception_handler();
            restore_error_handler();
        }

        return $eHandler;
    }
}
