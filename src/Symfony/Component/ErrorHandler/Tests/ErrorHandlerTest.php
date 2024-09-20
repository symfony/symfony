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
    protected function tearDown(): void
    {
        $r = new \ReflectionProperty(ErrorHandler::class, 'exitCode');
        $r->setValue(null, 0);
    }

    public function testRegister()
    {
        $handler = ErrorHandler::register();

        try {
            $this->assertInstanceOf(ErrorHandler::class, $handler);
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
        $logger = $this->createMock(LoggerInterface::class);
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
            $this->assertSame($expected, error_get_last());
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
            $this->fail('ErrorException expected');
        } catch (\ErrorException $exception) {
            // if an exception is thrown, the test passed
            $this->assertEquals(\E_WARNING, $exception->getSeverity());
            $this->assertMatchesRegularExpression('/^Warning: Undefined variable \$(foo|bar)/', $exception->getMessage());
            $this->assertEquals(__FILE__, $exception->getFile());

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
    public static function triggerNotice($that)
    {
        $that->assertSame('', $foo.$foo.$bar);
    }

    public function testFailureCall()
    {
        $this->expectException(\ErrorException::class);
        $this->expectExceptionMessageMatches('/^fopen\(unknown\.txt\): [Ff]ailed to open stream: No such file or directory$/');

        ErrorHandler::call('fopen', 'unknown.txt', 'r');
    }

    public function testCallRestoreErrorHandler()
    {
        $prev = set_error_handler('var_dump');
        try {
            ErrorHandler::call('fopen', 'unknown.txt', 'r');
            $this->fail('An \ErrorException should have been raised');
        } catch (\ErrorException $e) {
            $prev = set_error_handler($prev);
            restore_error_handler();
        } finally {
            restore_error_handler();
        }

        $this->assertSame('var_dump', $prev);
    }

    public function testCallErrorExceptionInfo()
    {
        try {
            ErrorHandler::call([self::class, 'triggerNotice'], $this);
            $this->fail('An \ErrorException should have been raised');
        } catch (\ErrorException $e) {
            $trace = $e->getTrace();
            $this->assertEquals(\E_WARNING, $e->getSeverity());
            $this->assertSame('Undefined variable $foo', $e->getMessage());
            $this->assertSame(__FILE__, $e->getFile());
            $this->assertSame(0, $e->getCode());
            $this->assertStringMatchesFormat('%A{closure%A}', $trace[0]['function']);
            $this->assertSame(ErrorHandler::class, $trace[0]['class']);
            $this->assertSame('triggerNotice', $trace[1]['function']);
            $this->assertSame(__CLASS__, $trace[1]['class']);
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
            $this->assertEquals(3 | \E_RECOVERABLE_ERROR | \E_USER_ERROR, $handler->throwAt(0));
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    public function testDefaultLogger()
    {
        try {
            $logger = $this->createMock(LoggerInterface::class);
            $handler = ErrorHandler::register();

            $handler->setDefaultLogger($logger, \E_NOTICE);
            $handler->setDefaultLogger($logger, [\E_USER_NOTICE => LogLevel::CRITICAL]);

            $loggers = [
                \E_DEPRECATED => [null, LogLevel::INFO],
                \E_USER_DEPRECATED => [null, LogLevel::INFO],
                \E_NOTICE => [$logger, LogLevel::ERROR],
                \E_USER_NOTICE => [$logger, LogLevel::CRITICAL],
                \E_WARNING => [null, LogLevel::ERROR],
                \E_USER_WARNING => [null, LogLevel::ERROR],
                \E_COMPILE_WARNING => [null, LogLevel::ERROR],
                \E_CORE_WARNING => [null, LogLevel::ERROR],
                \E_USER_ERROR => [null, LogLevel::CRITICAL],
                \E_RECOVERABLE_ERROR => [null, LogLevel::CRITICAL],
                \E_COMPILE_ERROR => [null, LogLevel::CRITICAL],
                \E_PARSE => [null, LogLevel::CRITICAL],
                \E_ERROR => [null, LogLevel::CRITICAL],
                \E_CORE_ERROR => [null, LogLevel::CRITICAL],
            ];

            if (\PHP_VERSION_ID < 80400) {
                $loggers[\E_STRICT] = [null, LogLevel::ERROR];
            }

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
            $handler->throwAt(\E_USER_DEPRECATED, true);
            $this->assertFalse($handler->handleError(\E_USER_DEPRECATED, 'foo', 'foo.php', 12, []));

            restore_error_handler();
            restore_exception_handler();

            $handler = ErrorHandler::register();
            $handler->throwAt(\E_DEPRECATED, true);
            $this->assertFalse($handler->handleError(\E_DEPRECATED, 'foo', 'foo.php', 12, []));

            restore_error_handler();
            restore_exception_handler();

            $logger = $this->createMock(LoggerInterface::class);

            $warnArgCheck = function ($logLevel, $message, $context) {
                $this->assertEquals('info', $logLevel);
                $this->assertEquals('User Deprecated: foo', $message);
                $this->assertArrayHasKey('exception', $context);
                $exception = $context['exception'];
                $this->assertInstanceOf(\ErrorException::class, $exception);
                $this->assertSame('User Deprecated: foo', $exception->getMessage());
                $this->assertSame(\E_USER_DEPRECATED, $exception->getSeverity());
            };

            $logger
                ->expects($this->once())
                ->method('log')
                ->willReturnCallback($warnArgCheck)
            ;

            $handler = ErrorHandler::register();
            $handler->setDefaultLogger($logger, \E_USER_DEPRECATED);
            $this->assertTrue($handler->handleError(\E_USER_DEPRECATED, 'foo', 'foo.php', 12, []));

            restore_error_handler();
            restore_exception_handler();

            $logger = $this->createMock(LoggerInterface::class);

            $line = null;
            $logArgCheck = function ($level, $message, $context) use (&$line) {
                $this->assertArrayHasKey('exception', $context);
                $exception = $context['exception'];

                $this->assertEquals('Warning: Undefined variable $undefVar', $message);
                $this->assertSame(\E_WARNING, $exception->getSeverity());

                $this->assertInstanceOf(SilencedErrorContext::class, $exception);
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
            $handler->setDefaultLogger($logger, \E_WARNING);
            $handler->screamAt(\E_WARNING);
            unset($undefVar);
            $line = __LINE__ + 1;
            @$undefVar++;
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    public function testHandleErrorWithAnonymousClass()
    {
        $anonymousObject = new class extends \stdClass {
        };

        $handler = ErrorHandler::register();
        try {
            trigger_error('foo '.$anonymousObject::class.' bar', \E_USER_WARNING);
            $this->fail('Exception expected.');
        } catch (\ErrorException $e) {
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }

        $this->assertSame('User Warning: foo stdClass@anonymous bar', $e->getMessage());
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

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
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
    public function testHandleException(string $expectedMessage, \Throwable $exception, ?string $enhancedMessage = null)
    {
        try {
            $logger = $this->createMock(LoggerInterface::class);
            $handler = ErrorHandler::register();

            $logArgCheck = function ($level, $message, $context) use ($expectedMessage, $exception) {
                $this->assertSame('critical', $level);
                $this->assertSame($expectedMessage, $message);
                $this->assertArrayHasKey('exception', $context);
                $this->assertInstanceOf($exception::class, $context['exception']);
            };

            $logger
                ->expects($this->exactly(2))
                ->method('log')
                ->willReturnCallback($logArgCheck)
            ;

            $handler->setDefaultLogger($logger, \E_ERROR);
            $handler->setExceptionHandler(null);

            try {
                $handler->handleException($exception);
                $this->fail('Exception expected');
            } catch (\Throwable $e) {
                $this->assertInstanceOf($exception::class, $e);
                $this->assertSame($enhancedMessage ?? $exception->getMessage(), $e->getMessage());
            }

            $handler->setExceptionHandler(function ($e) use ($exception, $enhancedMessage) {
                $this->assertInstanceOf($exception::class, $e);
                $this->assertSame($enhancedMessage ?? $exception->getMessage(), $e->getMessage());
            });

            $handler->handleException($exception);
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    public static function handleExceptionProvider(): array
    {
        return [
            ['Uncaught Exception: foo', new \Exception('foo')],
            ['Uncaught Exception: foo', new class('foo') extends \RuntimeException {
            }],
            ['Uncaught Exception: foo stdClass@anonymous bar', new \RuntimeException('foo '.(new class extends \stdClass {
            })::class.' bar')],
            ['Uncaught Error: bar', new \Error('bar')],
            ['Uncaught ccc', new \ErrorException('ccc')],
            [
                'Uncaught Error: Class "App\Controller\ClassDoesNotExist" not found',
                new \Error('Class "App\Controller\ClassDoesNotExist" not found'),
                "Attempted to load class \"ClassDoesNotExist\" from namespace \"App\Controller\".\nDid you forget a \"use\" statement for another namespace?",
            ],
        ];
    }

    public function testBootstrappingLogger()
    {
        $bootLogger = new BufferingLogger();
        $handler = new ErrorHandler($bootLogger);

        $loggers = [
            \E_DEPRECATED => [$bootLogger, LogLevel::INFO],
            \E_USER_DEPRECATED => [$bootLogger, LogLevel::INFO],
            \E_NOTICE => [$bootLogger, LogLevel::ERROR],
            \E_USER_NOTICE => [$bootLogger, LogLevel::ERROR],
            \E_WARNING => [$bootLogger, LogLevel::ERROR],
            \E_USER_WARNING => [$bootLogger, LogLevel::ERROR],
            \E_COMPILE_WARNING => [$bootLogger, LogLevel::ERROR],
            \E_CORE_WARNING => [$bootLogger, LogLevel::ERROR],
            \E_USER_ERROR => [$bootLogger, LogLevel::CRITICAL],
            \E_RECOVERABLE_ERROR => [$bootLogger, LogLevel::CRITICAL],
            \E_COMPILE_ERROR => [$bootLogger, LogLevel::CRITICAL],
            \E_PARSE => [$bootLogger, LogLevel::CRITICAL],
            \E_ERROR => [$bootLogger, LogLevel::CRITICAL],
            \E_CORE_ERROR => [$bootLogger, LogLevel::CRITICAL],
        ];

        if (\PHP_VERSION_ID < 80400) {
            $loggers[\E_STRICT] = [$bootLogger, LogLevel::ERROR];
        }

        $this->assertSame($loggers, $handler->setLoggers([]));

        $handler->handleError(\E_DEPRECATED, 'Foo message', __FILE__, 123, []);

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
        $this->assertSame(\E_DEPRECATED, $exception->getSeverity());

        $bootLogger->log(LogLevel::WARNING, 'Foo message', ['exception' => $exception]);

        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockLogger->expects($this->once())
            ->method('log')
            ->with(LogLevel::WARNING, 'Foo message', ['exception' => $exception]);

        $handler->setLoggers([\E_DEPRECATED => [$mockLogger, LogLevel::WARNING]]);
    }

    public function testSettingLoggerWhenExceptionIsBuffered()
    {
        $bootLogger = new BufferingLogger();
        $handler = new ErrorHandler($bootLogger);

        $exception = new \Exception('Foo message');

        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockLogger->expects($this->once())
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
            $logger = $this->createMock(LoggerInterface::class);
            $handler = ErrorHandler::register();

            $error = [
                'type' => \E_PARSE,
                'message' => 'foo',
                'file' => 'bar',
                'line' => 123,
            ];

            $logArgCheck = function ($level, $message, $context) {
                $this->assertEquals('Fatal Parse Error: foo', $message);
                $this->assertArrayHasKey('exception', $context);
                $this->assertInstanceOf(FatalError::class, $context['exception']);
            };

            $logger
                ->expects($this->once())
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

        $this->assertInstanceOf(ClassNotFoundError::class, $args[0]);
        $this->assertStringStartsWith("Attempted to load class \"IReallyReallyDoNotExistAnywhereInTheRepositoryISwear\" from the global namespace.\nDid you forget a \"use\" statement", $args[0]->getMessage());
    }

    public function testCustomExceptionHandler()
    {
        $this->expectException(\Exception::class);
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

    public static function errorHandlerWhenLoggingProvider(): iterable
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
            $this->markTestSkipped('zend.assertions is forcibly disabled');
        }

        set_error_handler(function () {});
        $ini = [
            ini_set('zend.assertions', 1),
            ini_set('assert.active', 1),
            ini_set('assert.bail', 0),
            ini_set('assert.warning', 1),
            ini_set('assert.callback', null),
            ini_set('assert.exception', 0),
        ];
        restore_error_handler();

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

        $this->assertSame('error', $logs[0][0]);
        $this->assertSame('Warning: assert(): assert(false) failed', $logs[0][1]);
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

            $this->assertSame($expectedLine, $exception->getLine());
            $this->assertSame(__FILE__, $exception->getFile());

            $frame = $exception->getTrace()[0];
            $this->assertSame(__CLASS__, $frame['class']);
            $this->assertSame(__FUNCTION__, $frame['function']);
            $this->assertSame('->', $frame['type']);
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }
}
