<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Kafka\Tests\Callback;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use RdKafka\KafkaConsumer;
use Symfony\Component\Messenger\Bridge\Kafka\Callback\LoggingLogCallback;

/**
 * @requires extension rdkafka
 */
final class LoggingLogCallbackTest extends TestCase
{
    public function getLogLevels(): iterable
    {
        yield [0, LogLevel::EMERGENCY];
        yield [1, LogLevel::ALERT];
        yield [2, LogLevel::CRITICAL];
        yield [3, LogLevel::ERROR];
        yield [4, LogLevel::WARNING];
        yield [5, LogLevel::NOTICE];
        yield [6, LogLevel::INFO];
        yield [7, LogLevel::DEBUG];
        yield [8, LogLevel::DEBUG];
    }

    /**
     * @dataProvider getLogLevels
     */
    public function testInvoke(int $level, $expectedLevel)
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('log')
            ->with($expectedLevel, 'test error message', ['facility' => 'facility-value']);

        $consumer = $this->createMock(KafkaConsumer::class);

        $callback = new LoggingLogCallback($logger);
        $callback($consumer, $level, 'facility-value', 'test error message');
    }
}
