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
use RdKafka\KafkaConsumer;
use Symfony\Component\Messenger\Bridge\Kafka\Callback\LoggingErrorCallback;

/**
 * @requires extension rdkafka
 */
final class LoggingErrorCallbackTest extends TestCase
{
    public function testInvoke()
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with('test error message', ['error_code' => 1]);

        $consumer = $this->createMock(KafkaConsumer::class);

        $callback = new LoggingErrorCallback($logger);
        $callback($consumer, 1, 'test error message');
    }
}
