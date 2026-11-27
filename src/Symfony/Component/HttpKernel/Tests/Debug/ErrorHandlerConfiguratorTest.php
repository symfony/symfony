<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Debug;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use Symfony\Component\ErrorHandler\BufferingLogger;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\HttpKernel\Debug\ErrorHandlerConfigurator;

class ErrorHandlerConfiguratorTest extends TestCase
{
    private int $errorReporting;

    protected function setUp(): void
    {
        $this->errorReporting = error_reporting(\E_ALL);
    }

    protected function tearDown(): void
    {
        error_reporting($this->errorReporting);
    }

    public function testConfigureDoesNotUseLegacyLoggerConfiguration()
    {
        if (!method_exists(ErrorHandler::class, 'configure')) {
            $this->markTestSkipped('The error handler does not support configuring the default loggers.');
        }

        $logger = new BufferingLogger();
        $deprecationLogger = new BufferingLogger();
        $handler = new class extends ErrorHandler {
            public function setDefaultLogger(LoggerInterface $logger, array|int|null $levels = \E_ALL, bool $replace = false): void
            {
                throw new \LogicException('Legacy logger configuration was called.');
            }
        };

        (new ErrorHandlerConfigurator($logger, \E_ALL, 0, true, true, $deprecationLogger))->configure($handler);
        $handler->handleError(\E_USER_WARNING, 'Warning', __FILE__, __LINE__);
        $handler->handleError(\E_USER_DEPRECATED, 'Deprecation', __FILE__, __LINE__);

        $this->assertSame('User Warning: Warning', $logger->cleanLogs()[0][1] ?? null);
        $this->assertSame('User Deprecated: Deprecation', $deprecationLogger->cleanLogs()[0][1] ?? null);
    }

    public function testConfigure()
    {
        $logger = new BufferingLogger();
        $configurator = new ErrorHandlerConfigurator($logger);
        $handler = new ErrorHandler();

        $configurator->configure($handler);

        $handler->handleError(\E_USER_DEPRECATED, 'Deprecation', __FILE__, __LINE__);
        $logs = $logger->cleanLogs();

        $this->assertSame(LogLevel::INFO, $logs[0][0] ?? null);
        $this->assertSame('User Deprecated: Deprecation', $logs[0][1] ?? null);
    }

    public function testConfigureReplacesTheLoggersOfAPreviousConfigurator()
    {
        $bootstrappingLogger = new BufferingLogger();
        $handler = new ErrorHandler($bootstrappingLogger);
        $handler->handleError(\E_USER_DEPRECATED, 'Before boot', __FILE__, __LINE__);

        $firstLogger = new BufferingLogger();
        $firstDeprecationLogger = new BufferingLogger();
        (new ErrorHandlerConfigurator($firstLogger, \E_ALL, 0, true, true, $firstDeprecationLogger))->configure($handler);

        $this->assertSame([], $bootstrappingLogger->cleanLogs());
        $this->assertSame('User Deprecated: Before boot', $firstDeprecationLogger->cleanLogs()[0][1] ?? null);

        $secondLogger = new BufferingLogger();
        $secondDeprecationLogger = new BufferingLogger();
        (new ErrorHandlerConfigurator($secondLogger, \E_ALL, 0, true, true, $secondDeprecationLogger))->configure($handler);

        $handler->handleError(\E_USER_WARNING, 'Foo', __FILE__, __LINE__);
        $handler->handleError(\E_USER_DEPRECATED, 'Bar', __FILE__, __LINE__);

        $this->assertSame([], $firstLogger->cleanLogs());
        $this->assertSame([], $firstDeprecationLogger->cleanLogs());
        $this->assertSame('User Warning: Foo', $secondLogger->cleanLogs()[0][1] ?? null);
        $this->assertSame('User Deprecated: Bar', $secondDeprecationLogger->cleanLogs()[0][1] ?? null);
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    #[DataProvider('provideIndependentDebugModes')]
    public function testConfigureKeepsIndependentDebugModes(bool $scream, bool $scope)
    {
        $firstLogger = new BufferingLogger();
        $logger = new BufferingLogger();
        $handler = new ErrorHandler();
        (new ErrorHandlerConfigurator($firstLogger, \E_ALL, 0, false, false))->configure($handler);
        (new ErrorHandlerConfigurator($logger, \E_ALL, 0, $scream, $scope))->configure($handler);

        $previousReporting = error_reporting(0);
        try {
            $handler->handleError(\E_USER_WARNING, 'Silenced warning', __FILE__, __LINE__);
        } finally {
            error_reporting($previousReporting);
        }

        $this->assertSame([], $firstLogger->cleanLogs());
        $this->assertCount((int) $scream, $logger->cleanLogs());

        $handler->handleError(\E_USER_WARNING, 'Warning', __FILE__, __LINE__);
        $this->assertSame('User Warning: Warning', $logger->cleanLogs()[0][1] ?? null);
    }

    public static function provideIndependentDebugModes(): iterable
    {
        yield [true, false];
        yield [false, true];
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testConfigureKeepsCustomThrownErrors()
    {
        $logger = new BufferingLogger();
        $handler = new ErrorHandler();
        (new ErrorHandlerConfigurator($logger, \E_ALL, \E_WARNING))->configure($handler);

        $handler->handleError(\E_USER_NOTICE, 'Notice', __FILE__, __LINE__);
        $this->assertSame('User Notice: Notice', $logger->cleanLogs()[0][1] ?? null);

        $this->expectException(\ErrorException::class);
        $this->expectExceptionMessage('Warning: Warning');
        $handler->handleError(\E_WARNING, 'Warning', __FILE__, __LINE__);
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testConfigureKeepsTheLoggersSetOnTheHandler()
    {
        $appLogger = new NullLogger();
        $handler = new ErrorHandler();
        $handler->setLoggers([\E_NOTICE => [$appLogger, LogLevel::ERROR]]);

        (new ErrorHandlerConfigurator(new NullLogger()))->configure($handler);

        $otherAppLogger = new NullLogger();
        $handler->setLoggers([\E_USER_NOTICE => [$otherAppLogger, LogLevel::ERROR]]);

        $logger = new NullLogger();
        (new ErrorHandlerConfigurator($logger))->configure($handler);

        $loggers = $handler->setLoggers([]);

        $this->assertSame([$appLogger, LogLevel::ERROR], $loggers[\E_NOTICE]);
        $this->assertSame([$otherAppLogger, LogLevel::ERROR], $loggers[\E_USER_NOTICE]);
        $this->assertSame($logger, $loggers[\E_WARNING][0]);
    }

    #[DataProvider('provideLevelsAssignedToLoggers')]
    public function testLevelsAssignedToLoggers(bool $hasLogger, bool $hasDeprecationLogger, array|int $levels, array|int|null $expectedLoggerLevels, array|int|null $expectedDeprecationLoggerLevels)
    {
        $handler = new ErrorHandler();
        $logger = $hasLogger ? new BufferingLogger() : null;
        $deprecationLogger = $hasDeprecationLogger ? new BufferingLogger() : null;
        (new ErrorHandlerConfigurator($logger, $levels, 0, true, true, $deprecationLogger))->configure($handler);

        $types = [\E_WARNING => LogLevel::ERROR, \E_ERROR => LogLevel::CRITICAL, \E_DEPRECATED => LogLevel::INFO, \E_USER_DEPRECATED => LogLevel::INFO];
        $handled = [];
        foreach ($types as $type => $level) {
            $handled[] = $handler->handleError($type, 'Error', __FILE__, __LINE__);
        }
        if (!$logger && !$deprecationLogger) {
            $this->assertSame([false, false, false, false], $handled);

            return;
        }

        foreach ([[$logger, $expectedLoggerLevels], [$deprecationLogger, $expectedDeprecationLoggerLevels]] as [$targetLogger, $expectedLevels]) {
            if (!$targetLogger) {
                continue;
            }

            $expected = [];
            foreach ($types as $type => $level) {
                if (\is_array($expectedLevels) ? isset($expectedLevels[$type]) : ($type & ($expectedLevels ?? 0))) {
                    $expected[] = [\is_array($expectedLevels) ? $expectedLevels[$type] : $level, $type];
                }
            }

            $this->assertSame($expected, array_map(static fn (array $log) => [$log[0], $log[2]['exception']->getSeverity()], $targetLogger->cleanLogs()));
        }
    }

    public static function provideLevelsAssignedToLoggers(): iterable
    {
        yield [false, false, 0, null, null];
        yield [false, false, \E_ALL, null, null];
        yield [false, false, [], null, null];
        yield [false, false, [\E_WARNING => LogLevel::WARNING, \E_USER_DEPRECATED => LogLevel::NOTICE], null, null];

        yield [true, false, \E_ALL, \E_ALL, null];
        yield [true, false, \E_DEPRECATED, \E_DEPRECATED, null];
        yield [true, false, [], null, null];
        yield [true, false, [\E_WARNING => LogLevel::WARNING, \E_DEPRECATED => LogLevel::NOTICE], [\E_WARNING => LogLevel::WARNING, \E_DEPRECATED => LogLevel::NOTICE], null];

        yield [false, true, 0, null, null];
        yield [false, true, \E_ALL, null, \E_DEPRECATED | \E_USER_DEPRECATED];
        yield [false, true, \E_ERROR, null, null];
        yield [false, true, [], null, null];
        yield [false, true, [\E_ERROR => LogLevel::ERROR, \E_DEPRECATED => LogLevel::DEBUG], null, [\E_DEPRECATED => LogLevel::DEBUG]];

        yield [true, true, 0, null, null];
        yield [true, true, \E_ALL, \E_ALL & ~(\E_DEPRECATED | \E_USER_DEPRECATED), \E_DEPRECATED | \E_USER_DEPRECATED];
        yield [true, true, \E_ERROR, \E_ERROR, null];
        yield [true, true, \E_USER_DEPRECATED, null, \E_USER_DEPRECATED];
        yield [true, true, [\E_ERROR => LogLevel::ERROR, \E_DEPRECATED => LogLevel::DEBUG], [\E_ERROR => LogLevel::ERROR], [\E_DEPRECATED => LogLevel::DEBUG]];
        yield [true, true, [\E_ERROR => LogLevel::ALERT], [\E_ERROR => LogLevel::ALERT], null];
        yield [true, true, [\E_USER_DEPRECATED => LogLevel::NOTICE], null, [\E_USER_DEPRECATED => LogLevel::NOTICE]];
    }
}
