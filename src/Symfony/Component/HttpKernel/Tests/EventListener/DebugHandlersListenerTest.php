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
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
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
        $logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();
        $userHandler = function () {};
        $listener = new DebugHandlersListener($userHandler, $logger);
        $eHandler = new ErrorHandler();

        $exception = null;
        set_error_handler([$eHandler, 'handleError']);
        set_exception_handler([$eHandler, 'handleException']);
        try {
            $listener->configure();
        } catch (\Exception $exception) {
        }
        restore_exception_handler();
        restore_error_handler();

        if (null !== $exception) {
            throw $exception;
        }

        $this->assertSame($userHandler, $eHandler->setExceptionHandler('var_dump'));

        $loggers = $eHandler->setLoggers([]);

        $this->assertArrayHasKey(\E_DEPRECATED, $loggers);
        $this->assertSame([$logger, LogLevel::INFO], $loggers[\E_DEPRECATED]);
    }

    public function testConfigureForHttpKernelWithNoTerminateWithException()
    {
        $listener = new DebugHandlersListener(null);
        $eHandler = new ErrorHandler();
        $event = new KernelEvent(
            $this->getMockBuilder('Symfony\Component\HttpKernel\HttpKernelInterface')->getMock(),
            Request::create('/'),
            HttpKernelInterface::MASTER_REQUEST
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
        $app = $this->getMockBuilder('Symfony\Component\Console\Application')->getMock();
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
        }
        restore_exception_handler();
        restore_error_handler();

        if (null !== $exception) {
            throw $exception;
        }

        $xHandler = $eHandler->setExceptionHandler('var_dump');
        $this->assertInstanceOf('Closure', $xHandler);

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

    public function provideLevelsAssignedToLoggers(): array
    {
        return [
            [false, false, '0', null, null],
            [false, false, \E_ALL, null, null],
            [false, false, [], null, null],
            [false, false, [\E_WARNING => LogLevel::WARNING, \E_USER_DEPRECATED => LogLevel::NOTICE], null, null],

            [true, false, \E_ALL, \E_ALL, null],
            [true, false, \E_DEPRECATED, \E_DEPRECATED, null],
            [true, false, [], null, null],
            [true, false, [\E_WARNING => LogLevel::WARNING, \E_DEPRECATED => LogLevel::NOTICE], [\E_WARNING => LogLevel::WARNING, \E_DEPRECATED => LogLevel::NOTICE], null],

            [false, true, '0', null, null],
            [false, true, \E_ALL, null, \E_DEPRECATED | \E_USER_DEPRECATED],
            [false, true, \E_ERROR, null, null],
            [false, true, [], null, null],
            [false, true, [\E_ERROR => LogLevel::ERROR, \E_DEPRECATED => LogLevel::DEBUG], null, [\E_DEPRECATED => LogLevel::DEBUG]],

            [true, true, '0', null, null],
            [true, true, \E_ALL, \E_ALL & ~(\E_DEPRECATED | \E_USER_DEPRECATED), \E_DEPRECATED | \E_USER_DEPRECATED],
            [true, true, \E_ERROR, \E_ERROR, null],
            [true, true, \E_USER_DEPRECATED, null, \E_USER_DEPRECATED],
            [true, true, [\E_ERROR => LogLevel::ERROR, \E_DEPRECATED => LogLevel::DEBUG], [\E_ERROR => LogLevel::ERROR], [\E_DEPRECATED => LogLevel::DEBUG]],
            [true, true, [\E_ERROR => LogLevel::ALERT], [\E_ERROR => LogLevel::ALERT], null],
            [true, true, [\E_USER_DEPRECATED => LogLevel::NOTICE], null, [\E_USER_DEPRECATED => LogLevel::NOTICE]],
        ];
    }

    /**
     * @dataProvider provideLevelsAssignedToLoggers
     *
     * @param array|string      $levels
     * @param array|string|null $expectedLoggerLevels
     * @param array|string|null $expectedDeprecationLoggerLevels
     */
    public function testLevelsAssignedToLoggers(bool $hasLogger, bool $hasDeprecationLogger, $levels, $expectedLoggerLevels, $expectedDeprecationLoggerLevels)
    {
        if (!class_exists(ErrorHandler::class)) {
            $this->markTestSkipped('ErrorHandler component is required to run this test.');
        }

        $handler = $this->createMock(ErrorHandler::class);

        $expectedCalls = [];
        $logger = null;

        $deprecationLogger = null;
        if ($hasDeprecationLogger) {
            $deprecationLogger = $this->createMock(LoggerInterface::class);
            if (null !== $expectedDeprecationLoggerLevels) {
                $expectedCalls[] = [$deprecationLogger, $expectedDeprecationLoggerLevels];
            }
        }

        if ($hasLogger) {
            $logger = $this->createMock(LoggerInterface::class);
            if (null !== $expectedLoggerLevels) {
                $expectedCalls[] = [$logger, $expectedLoggerLevels];
            }
        }

        $handler
            ->expects($this->exactly(\count($expectedCalls)))
            ->method('setDefaultLogger')
            ->withConsecutive(...$expectedCalls);

        $sut = new DebugHandlersListener(null, $logger, $levels, null, true, null, true, $deprecationLogger);
        $prevHander = set_exception_handler([$handler, 'handleError']);

        try {
            $handler
                ->method('handleError')
                ->willReturnCallback(function () use ($prevHander) {
                    $prevHander(...\func_get_args());
                });

            $sut->configure();
            set_exception_handler($prevHander);
        } catch (\Exception $e) {
            set_exception_handler($prevHander);
            throw $e;
        }
    }
}
