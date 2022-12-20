<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorHandler\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use Symfony\Component\ErrorHandler\BufferingLogger;
use Symfony\Component\ErrorHandler\Error\ClassNotFoundError;
use Symfony\Component\ErrorHandler\Error\FatalError;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\ErrorHandler\Exception\SilencedErrorContext;
use Symfony\Component\ErrorHandler\Tests\Fixtures\ErrorHandlerThatUsesThePreviousOne;
use Symfony\Component\ErrorHandler\Tests\Fixtures\LoggerThatSetAnErrorHandler;

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
            self::assertInstanceOf(ErrorHandler::class, $handler);
            self::assertSame($handler, ErrorHandler::register());

            $newHandler = new ErrorHandler();

            self::assertSame($handler, ErrorHandler::register($newHandler, false));
            $h = set_error_handler('var_dump');
            restore_error_handler();
            self::assertSame([$handler, 'handleError'], $h);

            try {
                self::assertSame($newHandler, ErrorHandler::register($newHandler, true));
                $h = set_error_handler('var_dump');
                restore_error_handler();
                self::assertSame([$newHandler, 'handleError'], $h);
            } finally {
                restore_error_handler();
                restore_exception_handler();
            }
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    public function testErrorGetLast()
    {
        $logger = self::createMock(LoggerInterface::class);
        $handler = ErrorHandler::register();
        $handler->setDefaultLogger($logger);
        $handler->screamAt(\E_ALL);

        try {
            @trigger_error('Hello', \E_USER_WARNING);
            $expected = [
                'type' => \E_USER_WARNING,
                'message' => 'Hello',
                'file' => __FILE__,
                'line' => __LINE__ - 5,
            ];
            self::assertSame($expected, error_get_last());
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    public function testNotice()
    {
        ErrorHandler::register();

        try {
            self::triggerNotice($this);
            self::fail('ErrorException expected');
        } catch (\ErrorException $exception) {
            // if an exception is thrown, the test passed
            if (\PHP_VERSION_ID < 80000) {
                self::assertEquals(\E_NOTICE, $exception->getSeverity());
                self::assertMatchesRegularExpression('/^Notice: Undefined variable: (foo|bar)/', $exception->getMessage());
            } else {
                self::assertEquals(\E_WARNING, $exception->getSeverity());
                self::assertMatchesRegularExpression('/^Warning: Undefined variable \$(foo|bar)/', $exception->getMessage());
            }
            self::assertEquals(__FILE__, $exception->getFile());

            $trace = $exception->getTrace();

            self::assertEquals(__FILE__, $trace[0]['file']);
            self::assertEquals(__CLASS__, $trace[0]['class']);
            self::assertEquals('triggerNotice', $trace[0]['function']);
            self::assertEquals('::', $trace[0]['type']);

            self::assertEquals(__FILE__, $trace[0]['file']);
            self::assertEquals(__CLASS__, $trace[1]['class']);
            self::assertEquals(__FUNCTION__, $trace[1]['function']);
            self::assertEquals('->', $trace[1]['type']);
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    // dummy function to test trace in error handler.
    public static function triggerNotice($that)
    {
        $that->assertSame('', $foo.$foo.$bar);
    }

    public function testFailureCall()
    {
        self::expectException(\ErrorException::class);
        self::expectExceptionMessageMatches('/^fopen\(unknown\.txt\): [Ff]ailed to open stream: No such file or directory$/');

        ErrorHandler::call('fopen', 'unknown.txt', 'r');
    }

    public function testCallRestoreErrorHandler()
    {
        $prev = set_error_handler('var_dump');
        try {
            ErrorHandler::call('fopen', 'unknown.txt', 'r');
            self::fail('An \ErrorException should have been raised');
        } catch (\ErrorException $e) {
            $prev = set_error_handler($prev);
            restore_error_handler();
        } finally {
            restore_error_handler();
        }

        self::assertSame('var_dump', $prev);
    }

    public function testCallErrorExceptionInfo()
    {
        try {
            ErrorHandler::call([self::class, 'triggerNotice'], $this);
            self::fail('An \ErrorException should have been raised');
        } catch (\ErrorException $e) {
            $trace = $e->getTrace();
            if (\PHP_VERSION_ID < 80000) {
                self::assertEquals(\E_NOTICE, $e->getSeverity());
                self::assertSame('Undefined variable: foo', $e->getMessage());
            } else {
                self::assertEquals(\E_WARNING, $e->getSeverity());
                self::assertSame('Undefined variable $foo', $e->getMessage());
            }
            self::assertSame(__FILE__, $e->getFile());
            self::assertSame(0, $e->getCode());
            self::assertSame('Symfony\Component\ErrorHandler\{closure}', $trace[0]['function']);
            self::assertSame(ErrorHandler::class, $trace[0]['class']);
            self::assertSame('triggerNotice', $trace[1]['function']);
            self::assertSame(__CLASS__, $trace[1]['class']);
        }
    }

    public function testSuccessCall()
    {
        touch($filename = tempnam(sys_get_temp_dir(), 'sf_error_handler_'));

        self::assertIsResource(ErrorHandler::call('fopen', $filename, 'r'));

        unlink($filename);
    }

    public function testConstruct()
    {
        try {
            $handler = ErrorHandler::register();
            $handler->throwAt(3, true);
            self::assertEquals(3 | \E_RECOVERABLE_ERROR | \E_USER_ERROR, $handler->throwAt(0));
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    public function testDefaultLogger()
    {
        try {
            $logger = self::createMock(LoggerInterface::class);
            $handler = ErrorHandler::register();

            $handler->setDefaultLogger($logger, \E_NOTICE);
            $handler->setDefaultLogger($logger, [\E_USER_NOTICE => LogLevel::CRITICAL]);

            $loggers = [
                \E_DEPRECATED => [null, LogLevel::INFO],
                \E_USER_DEPRECATED => [null, LogLevel::INFO],
                \E_NOTICE => [$logger, LogLevel::WARNING],
                \E_USER_NOTICE => [$logger, LogLevel::CRITICAL],
                \E_STRICT => [null, LogLevel::WARNING],
                \E_WARNING => [null, LogLevel::WARNING],
                \E_USER_WARNING => [null, LogLevel::WARNING],
                \E_COMPILE_WARNING => [null, LogLevel::WARNING],
                \E_CORE_WARNING => [null, LogLevel::WARNING],
                \E_USER_ERROR => [null, LogLevel::CRITICAL],
                \E_RECOVERABLE_ERROR => [null, LogLevel::CRITICAL],
                \E_COMPILE_ERROR => [null, LogLevel::CRITICAL],
                \E_PARSE => [null, LogLevel::CRITICAL],
                \E_ERROR => [null, LogLevel::CRITICAL],
                \E_CORE_ERROR => [null, LogLevel::CRITICAL],
            ];
            self::assertSame($loggers, $handler->setLoggers([]));
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
            self::assertFalse($handler->handleError(0, 'foo', 'foo.php', 12, []));

            restore_error_handler();
            restore_exception_handler();

            $handler = ErrorHandler::register();
            $handler->throwAt(3, true);
            self::assertFalse($handler->handleError(4, 'foo', 'foo.php', 12, []));

            restore_error_handler();
            restore_exception_handler();

            $handler = ErrorHandler::register();
            $handler->throwAt(3, true);
            try {
                $handler->handleError(4, 'foo', 'foo.php', 12, []);
            } catch (\ErrorException $e) {
                self::assertSame('Parse Error: foo', $e->getMessage());
                self::assertSame(4, $e->getSeverity());
                self::assertSame('foo.php', $e->getFile());
                self::assertSame(12, $e->getLine());
            }

            restore_error_handler();
            restore_exception_handler();

            $handler = ErrorHandler::register();
            $handler->throwAt(\E_USER_DEPRECATED, true);
            self::assertFalse($handler->handleError(\E_USER_DEPRECATED, 'foo', 'foo.php', 12, []));

            restore_error_handler();
            restore_exception_handler();

            $handler = ErrorHandler::register();
            $handler->throwAt(\E_DEPRECATED, true);
            self::assertFalse($handler->handleError(\E_DEPRECATED, 'foo', 'foo.php', 12, []));

            restore_error_handler();
            restore_exception_handler();

            $logger = self::createMock(LoggerInterface::class);

            $warnArgCheck = function ($logLevel, $message, $context) {
                self::assertEquals('info', $logLevel);
                self::assertEquals('User Deprecated: foo', $message);
                self::assertArrayHasKey('exception', $context);
                $exception = $context['exception'];
                self::assertInstanceOf(\ErrorException::class, $exception);
                self::assertSame('User Deprecated: foo', $exception->getMessage());
                self::assertSame(\E_USER_DEPRECATED, $exception->getSeverity());
            };

            $logger
                ->expects(self::once())
                ->method('log')
                ->willReturnCallback($warnArgCheck)
            ;

            $handler = ErrorHandler::register();
            $handler->setDefaultLogger($logger, \E_USER_DEPRECATED);
            self::assertTrue($handler->handleError(\E_USER_DEPRECATED, 'foo', 'foo.php', 12, []));

            restore_error_handler();
            restore_exception_handler();

            $logger = self::createMock(LoggerInterface::class);

            $line = null;
            $logArgCheck = function ($level, $message, $context) use (&$line) {
                self::assertArrayHasKey('exception', $context);
                $exception = $context['exception'];

                if (\PHP_VERSION_ID < 80000) {
                    self::assertEquals('Notice: Undefined variable: undefVar', $message);
                    self::assertSame(\E_NOTICE, $exception->getSeverity());
                } else {
                    self::assertEquals('Warning: Undefined variable $undefVar', $message);
                    self::assertSame(\E_WARNING, $exception->getSeverity());
                }

                self::assertInstanceOf(SilencedErrorContext::class, $exception);
                self::assertSame(__FILE__, $exception->getFile());
                self::assertSame($line, $exception->getLine());
                self::assertNotEmpty($exception->getTrace());
                self::assertSame(1, $exception->count);
            };

            $logger
                ->expects(self::once())
                ->method('log')
                ->willReturnCallback($logArgCheck)
            ;

            $handler = ErrorHandler::register();
            if (\PHP_VERSION_ID < 80000) {
                $handler->setDefaultLogger($logger, \E_NOTICE);
                $handler->screamAt(\E_NOTICE);
            } else {
                $handler->setDefaultLogger($logger, \E_WARNING);
                $handler->screamAt(\E_WARNING);
            }
            unset($undefVar);
            $line = __LINE__ + 1;
            @$undefVar++;
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    public function testHandleUserError()
    {
        if (\PHP_VERSION_ID >= 70400) {
            self::markTestSkipped('PHP 7.4 allows __toString to throw exceptions');
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

            self::assertSame($x, $e);
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    public function testHandleErrorWithAnonymousClass()
    {
        $anonymousObject = new class() extends \stdClass {
        };

        $handler = ErrorHandler::register();
        try {
            trigger_error('foo '.\get_class($anonymousObject).' bar', \E_USER_WARNING);
            self::fail('Exception expected.');
        } catch (\ErrorException $e) {
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }

        self::assertSame('User Warning: foo stdClass@anonymous bar', $e->getMessage());
    }

    public function testHandleDeprecation()
    {
        $logArgCheck = function ($level, $message, $context) {
            self::assertEquals(LogLevel::INFO, $level);
            self::assertArrayHasKey('exception', $context);
            $exception = $context['exception'];
            self::assertInstanceOf(\ErrorException::class, $exception);
            self::assertSame('User Deprecated: Foo deprecation', $exception->getMessage());
        };

        $logger = self::createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('log')
            ->willReturnCallback($logArgCheck)
        ;

        $handler = new ErrorHandler();
        $handler->setDefaultLogger($logger);
        @$handler->handleError(\E_USER_DEPRECATED, 'Foo deprecation', __FILE__, __LINE__, []);
    }

    /**
     * @dataProvider handleExceptionProvider
     */
    public function testHandleException(string $expectedMessage, \Throwable $exception)
    {
        try {
            $logger = self::createMock(LoggerInterface::class);
            $handler = ErrorHandler::register();

            $logArgCheck = function ($level, $message, $context) use ($expectedMessage, $exception) {
                self::assertSame($expectedMessage, $message);
                self::assertArrayHasKey('exception', $context);
                self::assertInstanceOf(\get_class($exception), $context['exception']);
            };

            $logger
                ->expects(self::exactly(2))
                ->method('log')
                ->willReturnCallback($logArgCheck)
            ;

            $handler->setDefaultLogger($logger, \E_ERROR);
            $handler->setExceptionHandler(null);

            try {
                $handler->handleException($exception);
                self::fail('Exception expected');
            } catch (\Throwable $e) {
                self::assertSame($exception, $e);
            }

            $handler->setExceptionHandler(function ($e) use ($exception) {
                self::assertSame($exception, $e);
            });

            $handler->handleException($exception);
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    public function handleExceptionProvider(): array
    {
        return [
            ['Uncaught Exception: foo', new \Exception('foo')],
            ['Uncaught Exception: foo', new class('foo') extends \RuntimeException {
            }],
            ['Uncaught Exception: foo stdClass@anonymous bar', new \RuntimeException('foo '.\get_class(new class() extends \stdClass {
            }).' bar')],
            ['Uncaught Error: bar', new \Error('bar')],
            ['Uncaught ccc', new \ErrorException('ccc')],
        ];
    }

    public function testBootstrappingLogger()
    {
        $bootLogger = new BufferingLogger();
        $handler = new ErrorHandler($bootLogger);

        $loggers = [
            \E_DEPRECATED => [$bootLogger, LogLevel::INFO],
            \E_USER_DEPRECATED => [$bootLogger, LogLevel::INFO],
            \E_NOTICE => [$bootLogger, LogLevel::WARNING],
            \E_USER_NOTICE => [$bootLogger, LogLevel::WARNING],
            \E_STRICT => [$bootLogger, LogLevel::WARNING],
            \E_WARNING => [$bootLogger, LogLevel::WARNING],
            \E_USER_WARNING => [$bootLogger, LogLevel::WARNING],
            \E_COMPILE_WARNING => [$bootLogger, LogLevel::WARNING],
            \E_CORE_WARNING => [$bootLogger, LogLevel::WARNING],
            \E_USER_ERROR => [$bootLogger, LogLevel::CRITICAL],
            \E_RECOVERABLE_ERROR => [$bootLogger, LogLevel::CRITICAL],
            \E_COMPILE_ERROR => [$bootLogger, LogLevel::CRITICAL],
            \E_PARSE => [$bootLogger, LogLevel::CRITICAL],
            \E_ERROR => [$bootLogger, LogLevel::CRITICAL],
            \E_CORE_ERROR => [$bootLogger, LogLevel::CRITICAL],
        ];

        self::assertSame($loggers, $handler->setLoggers([]));

        $handler->handleError(\E_DEPRECATED, 'Foo message', __FILE__, 123, []);

        $logs = $bootLogger->cleanLogs();

        self::assertCount(1, $logs);
        $log = $logs[0];
        self::assertSame('info', $log[0]);
        self::assertSame('Deprecated: Foo message', $log[1]);
        self::assertArrayHasKey('exception', $log[2]);
        $exception = $log[2]['exception'];
        self::assertInstanceOf(\ErrorException::class, $exception);
        self::assertSame('Deprecated: Foo message', $exception->getMessage());
        self::assertSame(__FILE__, $exception->getFile());
        self::assertSame(123, $exception->getLine());
        self::assertSame(\E_DEPRECATED, $exception->getSeverity());

        $bootLogger->log(LogLevel::WARNING, 'Foo message', ['exception' => $exception]);

        $mockLogger = self::createMock(LoggerInterface::class);
        $mockLogger->expects(self::once())
            ->method('log')
            ->with(LogLevel::WARNING, 'Foo message', ['exception' => $exception]);

        $handler->setLoggers([\E_DEPRECATED => [$mockLogger, LogLevel::WARNING]]);
    }

    public function testSettingLoggerWhenExceptionIsBuffered()
    {
        $bootLogger = new BufferingLogger();
        $handler = new ErrorHandler($bootLogger);

        $exception = new \Exception('Foo message');

        $mockLogger = self::createMock(LoggerInterface::class);
        $mockLogger->expects(self::once())
            ->method('log')
            ->with(LogLevel::CRITICAL, 'Uncaught Exception: Foo message', ['exception' => $exception]);

        $handler->setExceptionHandler(function () use ($handler, $mockLogger) {
            $handler->setDefaultLogger($mockLogger);
        });

        $handler->handleException($exception);
    }

    public function testHandleFatalError()
    {
        try {
            $logger = self::createMock(LoggerInterface::class);
            $handler = ErrorHandler::register();

            $error = [
                'type' => \E_PARSE,
                'message' => 'foo',
                'file' => 'bar',
                'line' => 123,
            ];

            $logArgCheck = function ($level, $message, $context) {
                self::assertEquals('Fatal Parse Error: foo', $message);
                self::assertArrayHasKey('exception', $context);
                self::assertInstanceOf(FatalError::class, $context['exception']);
            };

            $logger
                ->expects(self::once())
                ->method('log')
                ->willReturnCallback($logArgCheck)
            ;

            $handler->setDefaultLogger($logger, \E_PARSE);
            $handler->setExceptionHandler(null);

            $handler->handleFatalError($error);
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    public function testHandleErrorException()
    {
        $exception = new \Error("Class 'IReallyReallyDoNotExistAnywhereInTheRepositoryISwear' not found");

        $handler = new ErrorHandler();
        $handler->setExceptionHandler(function () use (&$args) {
            $args = \func_get_args();
        });

        $handler->handleException($exception);

        self::assertInstanceOf(ClassNotFoundError::class, $args[0]);
        self::assertStringStartsWith("Attempted to load class \"IReallyReallyDoNotExistAnywhereInTheRepositoryISwear\" from the global namespace.\nDid you forget a \"use\" statement", $args[0]->getMessage());
    }

    public function testCustomExceptionHandler()
    {
        self::expectException(\Exception::class);
        $handler = new ErrorHandler();
        $handler->setExceptionHandler(function ($e) use ($handler) {
            $handler->setExceptionHandler(null);
            $handler->handleException($e);
        });

        $handler->handleException(new \Exception());
    }

    public function testRenderException()
    {
        $handler = new ErrorHandler();
        $handler->setExceptionHandler([$handler, 'renderException']);

        ob_start();
        $handler->handleException(new \RuntimeException('Class Foo not found'));
        $response = ob_get_clean();

        self::assertStringContainsString('Class Foo not found', $response);
    }

    /**
     * @dataProvider errorHandlerWhenLoggingProvider
     */
    public function testErrorHandlerWhenLogging(bool $previousHandlerWasDefined, bool $loggerSetsAnotherHandler, bool $nextHandlerIsDefined)
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

            @trigger_error('foo', \E_USER_DEPRECATED);
            @trigger_error('bar', \E_USER_DEPRECATED);

            self::assertSame([$handler, 'handleError'], set_error_handler('var_dump'));

            if ($logger instanceof LoggerThatSetAnErrorHandler) {
                self::assertCount(2, $logger->cleanLogs());
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

    public function errorHandlerWhenLoggingProvider(): iterable
    {
        foreach ([false, true] as $previousHandlerWasDefined) {
            foreach ([false, true] as $loggerSetsAnotherHandler) {
                foreach ([false, true] as $nextHandlerIsDefined) {
                    yield [$previousHandlerWasDefined, $loggerSetsAnotherHandler, $nextHandlerIsDefined];
                }
            }
        }
    }

    public function testAssertQuietEval()
    {
        if ('-1' === \ini_get('zend.assertions')) {
            self::markTestSkipped('zend.assertions is forcibly disabled');
        }

        $ini = [
            ini_set('zend.assertions', 1),
            ini_set('assert.active', 1),
            ini_set('assert.bail', 0),
            ini_set('assert.warning', 1),
            ini_set('assert.callback', null),
            ini_set('assert.exception', 0),
        ];

        $logger = new BufferingLogger();
        $handler = new ErrorHandler($logger);
        $handler = ErrorHandler::register($handler);

        try {
            \assert(false);
        } finally {
            restore_error_handler();
            restore_exception_handler();

            ini_set('zend.assertions', $ini[0]);
            ini_set('assert.active', $ini[1]);
            ini_set('assert.bail', $ini[2]);
            ini_set('assert.warning', $ini[3]);
            ini_set('assert.callback', $ini[4]);
            ini_set('assert.exception', $ini[5]);
        }

        $logs = $logger->cleanLogs();

        self::assertSame('warning', $logs[0][0]);
        self::assertSame('Warning: assert(): assert(false) failed', $logs[0][1]);
    }

    public function testHandleTriggerDeprecation()
    {
        try {
            $handler = ErrorHandler::register();
            $handler->setDefaultLogger($logger = new BufferingLogger());

            $expectedLine = __LINE__ + 1;
            trigger_deprecation('foo', '1.2.3', 'bar');

            /** @var \ErrorException $exception */
            $exception = $logger->cleanLogs()[0][2]['exception'];

            self::assertSame($expectedLine, $exception->getLine());
            self::assertSame(__FILE__, $exception->getFile());

            $frame = $exception->getTrace()[0];
            self::assertSame(__CLASS__, $frame['class']);
            self::assertSame(__FUNCTION__, $frame['function']);
            self::assertSame('->', $frame['type']);
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }
}
