<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Debug\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use Symfony\Component\Debug\BufferingLogger;
use Symfony\Component\Debug\ErrorHandler;
use Symfony\Component\Debug\Exception\SilencedErrorContext;
use Symfony\Component\Debug\Tests\Fixtures\ErrorHandlerThatUsesThePreviousOne;
use Symfony\Component\Debug\Tests\Fixtures\LoggerThatSetAnErrorHandler;

/**
 * ErrorHandlerTest.
 *
 * @author Robert Schönthal <seroscho@googlemail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ErrorHandlerTest extends TestCase
{
    public function testRegister()
    {
        $handler = ErrorHandler::register();

        try {
            $this->assertInstanceOf('Symfony\Component\Debug\ErrorHandler', $handler);
            $this->assertSame($handler, ErrorHandler::register());

            $newHandler = new ErrorHandler();

            $this->assertSame($handler, ErrorHandler::register($newHandler, false));
            $h = set_error_handler('var_dump');
            restore_error_handler();
            $this->assertSame([$handler, 'handleError'], $h);

            try {
                $this->assertSame($newHandler, ErrorHandler::register($newHandler, true));
                $h = set_error_handler('var_dump');
                restore_error_handler();
                $this->assertSame([$newHandler, 'handleError'], $h);
            } catch (\Exception $e) {
            }

            restore_error_handler();
            restore_exception_handler();

            if (isset($e)) {
                throw $e;
            }
        } catch (\Exception $e) {
        }

        restore_error_handler();
        restore_exception_handler();

        if (isset($e)) {
            throw $e;
        }
    }

    public function testErrorGetLast()
    {
        $logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();
        $handler = ErrorHandler::register();
        $handler->setDefaultLogger($logger);
        $handler->screamAt(E_ALL);

        try {
            @trigger_error('Hello', E_USER_WARNING);
            $expected = [
                'type' => E_USER_WARNING,
                'message' => 'Hello',
                'file' => __FILE__,
                'line' => __LINE__ - 5,
            ];
            $this->assertSame($expected, error_get_last());
        } catch (\Exception $e) {
            restore_error_handler();
            restore_exception_handler();

            throw $e;
        }
    }

    public function testNotice()
    {
        ErrorHandler::register();

        try {
            self::triggerNotice($this);
            $this->fail('ErrorException expected');
        } catch (\ErrorException $exception) {
            // if an exception is thrown, the test passed
            $this->assertEquals(E_NOTICE, $exception->getSeverity());
            $this->assertEquals(__FILE__, $exception->getFile());
            $this->assertRegExp('/^Notice: Undefined variable: (foo|bar)/', $exception->getMessage());

            $trace = $exception->getTrace();

            $this->assertEquals(__FILE__, $trace[0]['file']);
            $this->assertEquals(__CLASS__, $trace[0]['class']);
            $this->assertEquals('triggerNotice', $trace[0]['function']);
            $this->assertEquals('::', $trace[0]['type']);

            $this->assertEquals(__FILE__, $trace[0]['file']);
            $this->assertEquals(__CLASS__, $trace[1]['class']);
            $this->assertEquals(__FUNCTION__, $trace[1]['function']);
            $this->assertEquals('->', $trace[1]['type']);
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    // dummy function to test trace in error handler.
    private static function triggerNotice($that)
    {
        $that->assertSame('', $foo.$foo.$bar);
    }

    public function testConstruct()
    {
        try {
            $handler = ErrorHandler::register();
            $handler->throwAt(3, true);
            $this->assertEquals(3 | E_RECOVERABLE_ERROR | E_USER_ERROR, $handler->throwAt(0));
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    public function testDefaultLogger()
    {
        try {
            $logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();
            $handler = ErrorHandler::register();

            $handler->setDefaultLogger($logger, E_NOTICE);
            $handler->setDefaultLogger($logger, [E_USER_NOTICE => LogLevel::CRITICAL]);

            $loggers = [
                E_DEPRECATED => [null, LogLevel::INFO],
                E_USER_DEPRECATED => [null, LogLevel::INFO],
                E_NOTICE => [$logger, LogLevel::WARNING],
                E_USER_NOTICE => [$logger, LogLevel::CRITICAL],
                E_STRICT => [null, LogLevel::WARNING],
                E_WARNING => [null, LogLevel::WARNING],
                E_USER_WARNING => [null, LogLevel::WARNING],
                E_COMPILE_WARNING => [null, LogLevel::WARNING],
                E_CORE_WARNING => [null, LogLevel::WARNING],
                E_USER_ERROR => [null, LogLevel::CRITICAL],
                E_RECOVERABLE_ERROR => [null, LogLevel::CRITICAL],
                E_COMPILE_ERROR => [null, LogLevel::CRITICAL],
                E_PARSE => [null, LogLevel::CRITICAL],
                E_ERROR => [null, LogLevel::CRITICAL],
                E_CORE_ERROR => [null, LogLevel::CRITICAL],
            ];
            $this->assertSame($loggers, $handler->setLoggers([]));
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    public function testHandleError()
    {
        try {
            $handler = ErrorHandler::register();
            $handler->throwAt(0, true);
            $this->assertFalse($handler->handleError(0, 'foo', 'foo.php', 12, []));

            restore_error_handler();
            restore_exception_handler();

            $handler = ErrorHandler::register();
            $handler->throwAt(3, true);
            $this->assertFalse($handler->handleError(4, 'foo', 'foo.php', 12, []));

            restore_error_handler();
            restore_exception_handler();

            $handler = ErrorHandler::register();
            $handler->throwAt(3, true);
            try {
                $handler->handleError(4, 'foo', 'foo.php', 12, []);
            } catch (\ErrorException $e) {
                $this->assertSame('Parse Error: foo', $e->getMessage());
                $this->assertSame(4, $e->getSeverity());
                $this->assertSame('foo.php', $e->getFile());
                $this->assertSame(12, $e->getLine());
            }

            restore_error_handler();
            restore_exception_handler();

            $handler = ErrorHandler::register();
            $handler->throwAt(E_USER_DEPRECATED, true);
            $this->assertFalse($handler->handleError(E_USER_DEPRECATED, 'foo', 'foo.php', 12, []));

            restore_error_handler();
            restore_exception_handler();

            $handler = ErrorHandler::register();
            $handler->throwAt(E_DEPRECATED, true);
            $this->assertFalse($handler->handleError(E_DEPRECATED, 'foo', 'foo.php', 12, []));

            restore_error_handler();
            restore_exception_handler();

            $logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();

            $warnArgCheck = function ($logLevel, $message, $context) {
                $this->assertEquals('info', $logLevel);
                $this->assertEquals('User Deprecated: foo', $message);
                $this->assertArrayHasKey('exception', $context);
                $exception = $context['exception'];
                $this->assertInstanceOf(\ErrorException::class, $exception);
                $this->assertSame('User Deprecated: foo', $exception->getMessage());
                $this->assertSame(E_USER_DEPRECATED, $exception->getSeverity());
            };

            $logger
                ->expects($this->once())
                ->method('log')
                ->willReturnCallback($warnArgCheck)
            ;

            $handler = ErrorHandler::register();
            $handler->setDefaultLogger($logger, E_USER_DEPRECATED);
            $this->assertTrue($handler->handleError(E_USER_DEPRECATED, 'foo', 'foo.php', 12, []));

            restore_error_handler();
            restore_exception_handler();

            $logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();

            $line = null;
            $logArgCheck = function ($level, $message, $context) use (&$line) {
                $this->assertEquals('Notice: Undefined variable: undefVar', $message);
                $this->assertArrayHasKey('exception', $context);
                $exception = $context['exception'];
                $this->assertInstanceOf(SilencedErrorContext::class, $exception);
                $this->assertSame(E_NOTICE, $exception->getSeverity());
                $this->assertSame(__FILE__, $exception->getFile());
                $this->assertSame($line, $exception->getLine());
                $this->assertNotEmpty($exception->getTrace());
                $this->assertSame(1, $exception->count);
            };

            $logger
                ->expects($this->once())
                ->method('log')
                ->willReturnCallback($logArgCheck)
            ;

            $handler = ErrorHandler::register();
            $handler->setDefaultLogger($logger, E_NOTICE);
            $handler->screamAt(E_NOTICE);
            unset($undefVar);
            $line = __LINE__ + 1;
            @$undefVar++;

            restore_error_handler();
            restore_exception_handler();
        } catch (\Exception $e) {
            restore_error_handler();
            restore_exception_handler();

            throw $e;
        }
    }

    public function testHandleUserError()
    {
        if (\PHP_VERSION_ID >= 70400) {
            $this->markTestSkipped('PHP 7.4 allows __toString to throw exceptions');
        }

        try {
            $handler = ErrorHandler::register();
            $handler->throwAt(0, true);

            $e = null;
            $x = new \Exception('Foo');

            try {
                $f = new Fixtures\ToStringThrower($x);
                $f .= ''; // Trigger $f->__toString()
            } catch (\Exception $e) {
            }

            $this->assertSame($x, $e);
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    public function testHandleDeprecation()
    {
        $logArgCheck = function ($level, $message, $context) {
            $this->assertEquals(LogLevel::INFO, $level);
            $this->assertArrayHasKey('exception', $context);
            $exception = $context['exception'];
            $this->assertInstanceOf(\ErrorException::class, $exception);
            $this->assertSame('User Deprecated: Foo deprecation', $exception->getMessage());
        };

        $logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();
        $logger
            ->expects($this->once())
            ->method('log')
            ->willReturnCallback($logArgCheck)
        ;

        $handler = new ErrorHandler();
        $handler->setDefaultLogger($logger);
        @$handler->handleError(E_USER_DEPRECATED, 'Foo deprecation', __FILE__, __LINE__, []);
    }

    /**
     * @group no-hhvm
     */
    public function testHandleException()
    {
        try {
            $logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();
            $handler = ErrorHandler::register();

            $exception = new \Exception('foo');

            $logArgCheck = function ($level, $message, $context) {
                $this->assertSame('Uncaught Exception: foo', $message);
                $this->assertArrayHasKey('exception', $context);
                $this->assertInstanceOf(\Exception::class, $context['exception']);
            };

            $logger
                ->expects($this->exactly(2))
                ->method('log')
                ->willReturnCallback($logArgCheck)
            ;

            $handler->setDefaultLogger($logger, E_ERROR);

            try {
                $handler->handleException($exception);
                $this->fail('Exception expected');
            } catch (\Exception $e) {
                $this->assertSame($exception, $e);
            }

            $handler->setExceptionHandler(function ($e) use ($exception) {
                $this->assertSame($exception, $e);
            });

            $handler->handleException($exception);
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    /**
     * @group legacy
     */
    public function testErrorStacking()
    {
        try {
            $handler = ErrorHandler::register();
            $handler->screamAt(E_USER_WARNING);

            $logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();

            $logger
                ->expects($this->exactly(2))
                ->method('log')
                ->withConsecutive(
                    [$this->equalTo(LogLevel::WARNING), $this->equalTo('Dummy log')],
                    [$this->equalTo(LogLevel::DEBUG), $this->equalTo('User Warning: Silenced warning')]
                )
            ;

            $handler->setDefaultLogger($logger, [E_USER_WARNING => LogLevel::WARNING]);

            ErrorHandler::stackErrors();
            @trigger_error('Silenced warning', E_USER_WARNING);
            $logger->log(LogLevel::WARNING, 'Dummy log');
            ErrorHandler::unstackErrors();
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    public function testBootstrappingLogger()
    {
        $bootLogger = new BufferingLogger();
        $handler = new ErrorHandler($bootLogger);

        $loggers = [
            E_DEPRECATED => [$bootLogger, LogLevel::INFO],
            E_USER_DEPRECATED => [$bootLogger, LogLevel::INFO],
            E_NOTICE => [$bootLogger, LogLevel::WARNING],
            E_USER_NOTICE => [$bootLogger, LogLevel::WARNING],
            E_STRICT => [$bootLogger, LogLevel::WARNING],
            E_WARNING => [$bootLogger, LogLevel::WARNING],
            E_USER_WARNING => [$bootLogger, LogLevel::WARNING],
            E_COMPILE_WARNING => [$bootLogger, LogLevel::WARNING],
            E_CORE_WARNING => [$bootLogger, LogLevel::WARNING],
            E_USER_ERROR => [$bootLogger, LogLevel::CRITICAL],
            E_RECOVERABLE_ERROR => [$bootLogger, LogLevel::CRITICAL],
            E_COMPILE_ERROR => [$bootLogger, LogLevel::CRITICAL],
            E_PARSE => [$bootLogger, LogLevel::CRITICAL],
            E_ERROR => [$bootLogger, LogLevel::CRITICAL],
            E_CORE_ERROR => [$bootLogger, LogLevel::CRITICAL],
        ];

        $this->assertSame($loggers, $handler->setLoggers([]));

        $handler->handleError(E_DEPRECATED, 'Foo message', __FILE__, 123, []);

        $logs = $bootLogger->cleanLogs();

        $this->assertCount(1, $logs);
        $log = $logs[0];
        $this->assertSame('info', $log[0]);
        $this->assertSame('Deprecated: Foo message', $log[1]);
        $this->assertArrayHasKey('exception', $log[2]);
        $exception = $log[2]['exception'];
        $this->assertInstanceOf(\ErrorException::class, $exception);
        $this->assertSame('Deprecated: Foo message', $exception->getMessage());
        $this->assertSame(__FILE__, $exception->getFile());
        $this->assertSame(123, $exception->getLine());
        $this->assertSame(E_DEPRECATED, $exception->getSeverity());

        $bootLogger->log(LogLevel::WARNING, 'Foo message', ['exception' => $exception]);

        $mockLogger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();
        $mockLogger->expects($this->once())
            ->method('log')
            ->with(LogLevel::WARNING, 'Foo message', ['exception' => $exception]);

        $handler->setLoggers([E_DEPRECATED => [$mockLogger, LogLevel::WARNING]]);
    }

    /**
     * @group no-hhvm
     */
    public function testSettingLoggerWhenExceptionIsBuffered()
    {
        $bootLogger = new BufferingLogger();
        $handler = new ErrorHandler($bootLogger);

        $exception = new \Exception('Foo message');

        $mockLogger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();
        $mockLogger->expects($this->once())
            ->method('log')
            ->with(LogLevel::CRITICAL, 'Uncaught Exception: Foo message', ['exception' => $exception]);

        $handler->setExceptionHandler(function () use ($handler, $mockLogger) {
            $handler->setDefaultLogger($mockLogger);
        });

        $handler->handleException($exception);
    }

    /**
     * @group no-hhvm
     */
    public function testHandleFatalError()
    {
        try {
            $logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();
            $handler = ErrorHandler::register();

            $error = [
                'type' => E_PARSE,
                'message' => 'foo',
                'file' => 'bar',
                'line' => 123,
            ];

            $logArgCheck = function ($level, $message, $context) {
                $this->assertEquals('Fatal Parse Error: foo', $message);
                $this->assertArrayHasKey('exception', $context);
                $this->assertInstanceOf(\Exception::class, $context['exception']);
            };

            $logger
                ->expects($this->once())
                ->method('log')
                ->willReturnCallback($logArgCheck)
            ;

            $handler->setDefaultLogger($logger, E_PARSE);

            $handler->handleFatalError($error);

            restore_error_handler();
            restore_exception_handler();
        } catch (\Exception $e) {
            restore_error_handler();
            restore_exception_handler();

            throw $e;
        }
    }

    /**
     * @requires PHP 7
     */
    public function testHandleErrorException()
    {
        $exception = new \Error("Class 'IReallyReallyDoNotExistAnywhereInTheRepositoryISwear' not found");

        $handler = new ErrorHandler();
        $handler->setExceptionHandler(function () use (&$args) {
            $args = \func_get_args();
        });

        $handler->handleException($exception);

        $this->assertInstanceOf('Symfony\Component\Debug\Exception\ClassNotFoundException', $args[0]);
        $this->assertStringStartsWith("Attempted to load class \"IReallyReallyDoNotExistAnywhereInTheRepositoryISwear\" from the global namespace.\nDid you forget a \"use\" statement", $args[0]->getMessage());
    }

    /**
     * @group no-hhvm
     */
    public function testHandleFatalErrorOnHHVM()
    {
        try {
            $handler = ErrorHandler::register();

            $logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();
            $logger
                ->expects($this->once())
                ->method('log')
                ->with(
                    $this->equalTo(LogLevel::CRITICAL),
                    $this->equalTo('Fatal Error: foo')
                )
            ;

            $handler->setDefaultLogger($logger, E_ERROR);

            $error = [
                'type' => E_ERROR + 0x1000000, // This error level is used by HHVM for fatal errors
                'message' => 'foo',
                'file' => 'bar',
                'line' => 123,
                'context' => [123],
                'backtrace' => [456],
            ];

            \call_user_func_array([$handler, 'handleError'], $error);
            $handler->handleFatalError($error);
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    /**
     * @group no-hhvm
     */
    public function testCustomExceptionHandler()
    {
        $this->expectException('Exception');
        $handler = new ErrorHandler();
        $handler->setExceptionHandler(function ($e) use ($handler) {
            $handler->handleException($e);
        });

        $handler->handleException(new \Exception());
    }

    /**
     * @dataProvider errorHandlerWhenLoggingProvider
     */
    public function testErrorHandlerWhenLogging($previousHandlerWasDefined, $loggerSetsAnotherHandler, $nextHandlerIsDefined)
    {
        try {
            if ($previousHandlerWasDefined) {
                set_error_handler('count');
            }

            $logger = $loggerSetsAnotherHandler ? new LoggerThatSetAnErrorHandler() : new NullLogger();

            $handler = ErrorHandler::register();
            $handler->setDefaultLogger($logger);

            if ($nextHandlerIsDefined) {
                $handler = ErrorHandlerThatUsesThePreviousOne::register();
            }

            @trigger_error('foo', E_USER_DEPRECATED);
            @trigger_error('bar', E_USER_DEPRECATED);

            $this->assertSame([$handler, 'handleError'], set_error_handler('var_dump'));

            if ($logger instanceof LoggerThatSetAnErrorHandler) {
                $this->assertCount(2, $logger->cleanLogs());
            }

            restore_error_handler();

            if ($previousHandlerWasDefined) {
                restore_error_handler();
            }

            if ($nextHandlerIsDefined) {
                restore_error_handler();
            }
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    public function errorHandlerWhenLoggingProvider()
    {
        foreach ([false, true] as $previousHandlerWasDefined) {
            foreach ([false, true] as $loggerSetsAnotherHandler) {
                foreach ([false, true] as $nextHandlerIsDefined) {
                    yield [$previousHandlerWasDefined, $loggerSetsAnotherHandler, $nextHandlerIsDefined];
                }
            }
        }
    }
}
