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

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\HttpKernel\Debug\ErrorHandlerConfigurator;

class ErrorHandlerConfiguratorTest extends TestCase
{
    public function testConfigure()
    {
        $logger = $this->createMock(LoggerInterface::class);
        $configurator = new ErrorHandlerConfigurator($logger);
        $handler = new ErrorHandler();

        $configurator->configure($handler);

        $loggers = $handler->setLoggers([]);

        $this->assertArrayHasKey(\E_DEPRECATED, $loggers);
        $this->assertSame([$logger, LogLevel::INFO], $loggers[\E_DEPRECATED]);
    }

    /**
     * @dataProvider provideLevelsAssignedToLoggers
     */
    public function testLevelsAssignedToLoggers(bool $hasLogger, bool $hasDeprecationLogger, array|int $levels, array|int|null $expectedLoggerLevels, array|int|null $expectedDeprecationLoggerLevels)
    {
        $handler = $this->createMock(ErrorHandler::class);

        $expectedCalls = [];
        $logger = null;
        $deprecationLogger = null;

        if ($hasDeprecationLogger) {
            $deprecationLogger = $this->createMock(LoggerInterface::class);
            if (null !== $expectedDeprecationLoggerLevels) {
                $expectedCalls[] = [$deprecationLogger, $expectedDeprecationLoggerLevels, false];
            }
        }

        if ($hasLogger) {
            $logger = $this->createMock(LoggerInterface::class);
            if (null !== $expectedLoggerLevels) {
                $expectedCalls[] = [$logger, $expectedLoggerLevels, false];
            }
        }

        $handler
            ->expects($this->exactly(\count($expectedCalls)))
            ->method('setDefaultLogger')
            ->willReturnCallback(function (...$args) use (&$expectedCalls) {
                $this->assertSame(array_shift($expectedCalls), $args);
            })
        ;

        $configurator = new ErrorHandlerConfigurator($logger, $levels, null, true, true, $deprecationLogger);

        $configurator->configure($handler);
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
