<?php

declare(strict_types=1);

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
use Symfony\Component\Messenger\Bridge\Kafka\Callback\LoggingLogCallback;

/**
 * @requires extension rdkafka
 */
final class LoggingLogCallbackTest extends TestCase
{
    public function testInvoke(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('log')
            ->with(1, 'test error message', ['facility' => 'facility-value']);

        $consumer = $this->createMock(KafkaConsumer::class);

        $callback = new LoggingLogCallback($logger);
        $callback($consumer, 1, 'facility-value', 'test error message');
    }
}
