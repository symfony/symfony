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

namespace Symfony\Component\Messenger\Bridge\Kafka\Callback;

use RdKafka\KafkaConsumer;
use RdKafka\Message;
use RdKafka\Producer;

abstract class AbstractCallbackProcessor implements CallbackProcessorInterface
{
    public function log(object $kafka, int $level, string $facility, string $message): void
    {
    }

    public function consumerError(KafkaConsumer $kafka, int $err, string $reason): void
    {
    }

    public function producerError(Producer $kafka, int $err, string $reason): void
    {
    }

    public function stats(object $kafka, string $json, int $jsonLength): void
    {
    }

    public function rebalance(KafkaConsumer $kafka, int $err, array $partitions): void
    {
    }

    public function consume(Message $message): void
    {
    }

    public function offsetCommit(object $kafka, int $err, array $partitions): void
    {
    }

    public function deliveryReport(object $kafka, Message $message): void
    {
    }
}
