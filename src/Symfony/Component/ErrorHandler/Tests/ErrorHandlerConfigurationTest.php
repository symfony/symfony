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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use Symfony\Component\ErrorHandler\BufferingLogger;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\ErrorHandler\Exception\SilencedErrorContext;

class ErrorHandlerConfigurationTest extends TestCase
{
    private int $errorReporting;

    protected function setUp(): void
    {
        $this->errorReporting = error_reporting(\E_ALL);
    }

    protected function tearDown(): void
    {
        error_reporting($this->errorReporting);
        (new \ReflectionProperty(ErrorHandler::class, 'exitCode'))->setValue(null, 0);
    }

    public function testConfigureRoutesErrorsAndDeprecations()
    {
        $logger = new BufferingLogger();
        $deprecationLogger = new BufferingLogger();
        $handler = new ErrorHandler();
        $handler->configure($logger, $deprecationLogger, false, false);

        $handler->handleError(\E_WARNING, 'warning', __FILE__, __LINE__);
        $handler->handleError(\E_USER_DEPRECATED, 'deprecation', __FILE__, __LINE__);

        self::assertSame([[LogLevel::ERROR, 'Warning: warning']], array_map(static fn ($log) => [$log[0], $log[1]], $logger->cleanLogs()));
        self::assertSame([[LogLevel::INFO, 'User Deprecated: deprecation']], array_map(static fn ($log) => [$log[0], $log[1]], $deprecationLogger->cleanLogs()));
    }

    public function testConfigureCanDiscardDeprecations()
    {
        $logger = new BufferingLogger();
        $handler = new ErrorHandler();
        $handler->configure($logger, new NullLogger(), false, false);

        $handler->handleError(\E_WARNING, 'warning', __FILE__, __LINE__);
        $handler->handleError(\E_USER_DEPRECATED, 'deprecation', __FILE__, __LINE__);

        self::assertSame(['Warning: warning'], array_column($logger->cleanLogs(), 1));
    }

    public function testConfigureFlushesBootstrappingLogsToBothLoggers()
    {
        $bootLogger = new BufferingLogger();
        $handler = new ErrorHandler($bootLogger);
        $bootLogger->log(LogLevel::ERROR, 'warning', ['exception' => new \ErrorException('warning', 0, \E_WARNING)]);
        $bootLogger->log(LogLevel::INFO, 'deprecation', ['exception' => new \ErrorException('deprecation', 0, \E_USER_DEPRECATED)]);
        $logger = new BufferingLogger();
        $deprecationLogger = new BufferingLogger();

        $handler->configure($logger, $deprecationLogger, false, false, [\E_WARNING => LogLevel::NOTICE, \E_USER_DEPRECATED => LogLevel::WARNING]);

        self::assertSame([], $bootLogger->cleanLogs());
        self::assertSame([[LogLevel::NOTICE, 'warning']], array_map(static fn ($log) => [$log[0], $log[1]], $logger->cleanLogs()));
        self::assertSame([[LogLevel::WARNING, 'deprecation']], array_map(static fn ($log) => [$log[0], $log[1]], $deprecationLogger->cleanLogs()));
    }

    public function testConfigurePreservesLogsForLevelsWithoutAnApplicationLogger()
    {
        $bootLogger = new BufferingLogger();
        $handler = new ErrorHandler($bootLogger);
        $bootLogger->log(LogLevel::ERROR, 'warning', ['exception' => new \ErrorException('warning', 0, \E_WARNING)]);
        $bootLogger->log(LogLevel::INFO, 'deprecation', ['exception' => new \ErrorException('deprecation', 0, \E_USER_DEPRECATED)]);
        $deprecationLogger = new BufferingLogger();

        $handler->configure(null, $deprecationLogger);

        self::assertSame(['warning'], array_column($bootLogger->cleanLogs(), 1));
        self::assertSame(['deprecation'], array_column($deprecationLogger->cleanLogs(), 1));
    }

    public function testConfigurePreservesBufferedLogsWhenTheBootstrapLoggerWasConfigured()
    {
        $bootLogger = new BufferingLogger();
        $handler = new ErrorHandler($bootLogger);
        $handler->configure($bootLogger);
        $handler->handleError(\E_USER_DEPRECATED, 'boot deprecation', __FILE__, __LINE__);
        $logger = new BufferingLogger();

        $handler->configure($logger);

        self::assertSame([], $bootLogger->cleanLogs());
        self::assertSame(['User Deprecated: boot deprecation'], array_column($logger->cleanLogs(), 1));
    }

    #[DataProvider('provideDebugModes')]
    public function testConfigureCapturesSilencedErrorsOnlyInDebug(bool $debug)
    {
        $logger = new BufferingLogger();
        $handler = new ErrorHandler();
        $handler->configure($logger, null, $debug, false);
        $previousReporting = error_reporting(\E_ALL & ~\E_WARNING);

        try {
            self::assertFalse($handler->handleError(\E_WARNING, 'silenced '.(int) $debug, __FILE__, __LINE__));
        } finally {
            error_reporting($previousReporting);
        }

        $logs = $logger->cleanLogs();
        self::assertCount((int) $debug, $logs);
        if ($debug) {
            self::assertSame(LogLevel::DEBUG, $logs[0][0]);
            self::assertInstanceOf(SilencedErrorContext::class, $logs[0][2]['exception']);
        }
    }

    #[DataProvider('provideDebugModes')]
    public function testConfigureControlsArgumentsInLoggedTraces(bool $debug)
    {
        $previousIgnoreArgs = ini_set('zend.exception_ignore_args', '0');
        $logger = new BufferingLogger();
        $handler = new ErrorHandler();
        $handler->configure($logger, null, $debug, false);

        try {
            $handler->handleError(\E_WARNING, 'warning', __FILE__, __LINE__);
        } finally {
            ini_set('zend.exception_ignore_args', $previousIgnoreArgs);
        }

        $trace = $logger->cleanLogs()[0][2]['exception']->getTrace();
        self::assertNotEmpty($trace);
        self::assertSame($debug, \array_key_exists('args', $trace[0]));
    }

    public static function provideDebugModes(): iterable
    {
        yield 'production' => [false];
        yield 'debug' => [true];
    }

    public function testConfigureWithoutLoggersPreservesDiagnostics()
    {
        $logger = new BufferingLogger();
        $handler = new ErrorHandler();
        $handler->configure($logger, null, true, false);
        $handler->configure();
        $previousReporting = error_reporting(\E_ALL & ~\E_WARNING);

        try {
            $handler->handleError(\E_WARNING, 'still captured', __FILE__, __LINE__);
        } finally {
            error_reporting($previousReporting);
        }

        self::assertCount(1, $logger->cleanLogs());
    }

    public function testConfigureReplacesLoggersFromThePreviousConfiguration()
    {
        $firstLogger = new BufferingLogger();
        $firstDeprecationLogger = new BufferingLogger();
        $secondLogger = new BufferingLogger();
        $handler = new ErrorHandler();
        $handler->configure($firstLogger, $firstDeprecationLogger, false, false);
        $handler->configure($secondLogger);

        $handler->handleError(\E_WARNING, 'warning', __FILE__, __LINE__);
        $handler->handleError(\E_USER_DEPRECATED, 'deprecation', __FILE__, __LINE__);

        self::assertSame([], $firstLogger->cleanLogs());
        self::assertSame([], $firstDeprecationLogger->cleanLogs());
        self::assertSame(['Warning: warning', 'User Deprecated: deprecation'], array_column($secondLogger->cleanLogs(), 1));
    }

    public function testConfigureLimitsLoggedLevels()
    {
        $logger = new BufferingLogger();
        $handler = new ErrorHandler();
        $handler->configure($logger, null, false, false, [\E_USER_DEPRECATED => LogLevel::NOTICE]);

        self::assertFalse($handler->handleError(\E_WARNING, 'ignored', __FILE__, __LINE__));
        self::assertTrue($handler->handleError(\E_USER_DEPRECATED, 'deprecation', __FILE__, __LINE__));
        self::assertSame([[LogLevel::NOTICE, 'User Deprecated: deprecation']], array_map(static fn ($log) => [$log[0], $log[1]], $logger->cleanLogs()));
    }

    #[DataProvider('provideThrowModes')]
    public function testConfigureControlsThrowing(?bool $throwErrors)
    {
        $logger = new BufferingLogger();
        $handler = new ErrorHandler();
        $handler->configure(null, null, false, false);
        $handler->configure($logger, null, false, $throwErrors);

        if ($throwErrors) {
            $this->expectException(\ErrorException::class);
            $this->expectExceptionMessage('Warning: warning');
        }

        $handler->handleError(\E_WARNING, 'warning', __FILE__, __LINE__);

        self::assertCount(1, $logger->cleanLogs());
    }

    public static function provideThrowModes(): iterable
    {
        yield 'enabled' => [true];
        yield 'disabled' => [false];
        yield 'unchanged' => [null];
    }

    public function testConfigureAlwaysThrowsUserErrors()
    {
        $handler = new ErrorHandler();
        $handler->configure(null, null, false, false);

        $this->expectException(\ErrorException::class);
        $this->expectExceptionMessage('User Error: error');

        $handler->handleError(\E_USER_ERROR, 'error', __FILE__, __LINE__);
    }

    public function testCustomHandlerCanTakeResponsibilityForExceptionLogging()
    {
        $logger = new BufferingLogger();
        $handler = new ErrorHandler();
        $handler->configure($logger, null, false, false);
        $previous = static function () {};
        $handler->setExceptionHandler($previous);
        $exception = new \RuntimeException('exception');

        self::assertSame($previous, $handler->setExceptionHandler(static function (\Throwable $caught) use ($exception) {
            self::assertSame($exception, $caught);
        }, false));

        $handler->handleError(\E_WARNING, 'warning', __FILE__, __LINE__);
        $handler->handleError(\E_USER_DEPRECATED, 'deprecation', __FILE__, __LINE__);
        $handler->handleException($exception);

        self::assertSame(['Warning: warning', 'User Deprecated: deprecation'], array_column($logger->cleanLogs(), 1));
    }

    public function testCustomHandlerAlsoTakesResponsibilityForThrownWarnings()
    {
        $logger = new BufferingLogger();
        $handler = new ErrorHandler();
        $handler->configure($logger, null, false, true);
        $exception = new \ErrorException('warning', 0, \E_WARNING);
        $handler->setExceptionHandler(static function (\Throwable $caught) use ($exception) {
            self::assertSame($exception, $caught);
        }, false);

        $handler->handleException($exception);

        self::assertSame([], $logger->cleanLogs());
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testConfigurePreservesApplicationLoggers()
    {
        $this->expectUserDeprecationMessage('Since symfony/error-handler 8.3: Method "Symfony\Component\ErrorHandler\ErrorHandler::setLoggers()" is deprecated, use "configure()" instead.');

        $applicationLogger = new BufferingLogger();
        $firstLogger = new BufferingLogger();
        $secondLogger = new BufferingLogger();
        $handler = new ErrorHandler();
        $handler->setLoggers([\E_WARNING => [$applicationLogger, LogLevel::NOTICE]]);
        $handler->configure($firstLogger, null, false, false);
        $handler->configure($secondLogger);

        $handler->handleError(\E_WARNING, 'application warning', __FILE__, __LINE__);
        $handler->handleError(\E_USER_DEPRECATED, 'deprecation', __FILE__, __LINE__);

        self::assertSame([[LogLevel::NOTICE, 'Warning: application warning']], array_map(static fn ($log) => [$log[0], $log[1]], $applicationLogger->cleanLogs()));
        self::assertSame([], $firstLogger->cleanLogs());
        self::assertSame(['User Deprecated: deprecation'], array_column($secondLogger->cleanLogs(), 1));
    }

    #[DataProvider('provideDeprecatedSetters')]
    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testDeprecatedSetters(string $method, array $arguments, mixed $expected)
    {
        $this->expectUserDeprecationMessage(\sprintf('Since symfony/error-handler 8.3: Method "Symfony\Component\ErrorHandler\ErrorHandler::%s()" is deprecated, use "configure()" instead.', $method));

        $handler = new ErrorHandler();

        if ('setLoggers' === $method) {
            self::assertSame([null, LogLevel::ERROR], $handler->$method(...$arguments)[\E_WARNING]);
        } else {
            self::assertSame($expected, $handler->$method(...$arguments));
        }
    }

    public static function provideDeprecatedSetters(): iterable
    {
        yield 'default logger' => ['setDefaultLogger', [new BufferingLogger()], null];
        yield 'loggers' => ['setLoggers', [[]], null];
        yield 'throwing' => ['throwAt', [0, true], 0x1FFF];
        yield 'scope' => ['scopeAt', [0, true], 0x1FFF];
        yield 'trace' => ['traceAt', [0, true], 0x77FB];
        yield 'scream' => ['screamAt', [0, true], 0x55];
    }

    #[DataProvider('provideDeprecatedLoggerSetters')]
    #[Group('legacy')]
    #[IgnoreDeprecations]
    #[WithoutErrorHandler]
    public function testDeprecatedLoggerSettersReportTheirDeprecationToTheNewLogger(string $method, \Closure $setLogger)
    {
        $logger = new BufferingLogger();
        $handler = ErrorHandler::register();

        try {
            $setLogger($handler, $logger);
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }

        self::assertSame([\sprintf('User Deprecated: Since symfony/error-handler 8.3: Method "Symfony\Component\ErrorHandler\ErrorHandler::%s()" is deprecated, use "configure()" instead.', $method)], array_column($logger->cleanLogs(), 1));
    }

    public static function provideDeprecatedLoggerSetters(): iterable
    {
        yield 'default logger' => ['setDefaultLogger', static fn (ErrorHandler $handler, BufferingLogger $logger) => $handler->setDefaultLogger($logger)];
        yield 'loggers' => ['setLoggers', static fn (ErrorHandler $handler, BufferingLogger $logger) => $handler->setLoggers([\E_USER_DEPRECATED => [$logger]])];
    }
}
