<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Test;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Monolog\LogRecord;

trait LoggerAssertionsTrait
{
    public static function assertLogExists(string $expectedLog, string $level = Logger::DEBUG): void
    {
        self::ensureMonologHandlerIsAvailable();

        /** @var TestHandler $logger */
        $logger = self::getContainer()->get('monolog.handler.test');

        self::assertTrue($logger->hasRecordThatPasses(
            function (array|LogRecord $record) use ($expectedLog) {
                return $record['message'] === $expectedLog;
            },
            $level,
        ));
    }

    public static function assertLogMatches(string $expectedRegex, string $level = Logger::DEBUG): void
    {
        self::ensureMonologHandlerIsAvailable();

        /** @var TestHandler $logger */
        $logger = self::getContainer()->get('monolog.handler.test');

        self::assertTrue($logger->hasRecordThatMatches($expectedRegex, $level));
    }

    public static function assertLogContains(string $expectedLog, string $level = Logger::DEBUG): void
    {
        self::ensureMonologHandlerIsAvailable();

        /** @var TestHandler $logger */
        $logger = self::getContainer()->get('monolog.handler.test');

        self::assertTrue($logger->hasRecordThatContains($expectedLog, $level));
    }

    /**
     * @internal
     */
    private static function ensureMonologHandlerIsAvailable(): void
    {
        if (!self::getContainer()->has('monolog.handler.test')) {
            self::fail('The "monolog.handler.test" service is not available. Try registering the service "Monolog\Handler\TestHandler" as "monolog.handler.test" in your test configuration.');
        }
    }
}
