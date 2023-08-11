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
use RdKafka\TopicPartition;

/**
 * @see https://arnaud.le-blanc.net/php-rdkafka-doc/phpdoc/class.rdkafka-conf.html for more information on callback parameters.
 */
final class CallbackManager
{
    /**
     * @param list<CallbackProcessorInterface> $callbackProcessors
     */
    public function __construct(
        private readonly iterable $callbackProcessors,
    ) {
    }

    public function log(object $kafka, int $level, string $facility, string $message): void
    {
        foreach ($this->callbackProcessors as $callbackProcessor) {
            $callbackProcessor->log($kafka, $level, $facility, $message);
        }
    }

    public function consumerError(KafkaConsumer $kafka, int $err, string $reason): void
    {
        foreach ($this->callbackProcessors as $callbackProcessor) {
            $callbackProcessor->consumerError($kafka, $err, $reason);
        }
    }

    public function producerError(Producer $kafka, int $err, string $reason): void
    {
        foreach ($this->callbackProcessors as $callbackProcessor) {
            $callbackProcessor->producerError($kafka, $err, $reason);
        }
    }

    public function stats(object $kafka, string $json, int $jsonLength): void
    {
        foreach ($this->callbackProcessors as $callbackProcessor) {
            $callbackProcessor->stats($kafka, $json, $jsonLength);
        }
    }

    /**
     * @param list<TopicPartition> $partitions
     */
    public function rebalance(KafkaConsumer $kafka, int $err, array $partitions): void
    {
        foreach ($this->callbackProcessors as $callbackProcessor) {
            $callbackProcessor->rebalance($kafka, $err, $partitions);
        }
    }

    public function consume(Message $message): void
    {
        foreach ($this->callbackProcessors as $callbackProcessor) {
            $callbackProcessor->consume($message);
        }
    }

    /**
     * @param list<TopicPartition> $partitions
     */
    public function offsetCommit(object $kafka, int $err, array $partitions): void
    {
        foreach ($this->callbackProcessors as $callbackProcessor) {
            $callbackProcessor->offsetCommit($kafka, $err, $partitions);
        }
    }

    public function deliveryReport(object $kafka, Message $message): void
    {
        foreach ($this->callbackProcessors as $callbackProcessor) {
            $callbackProcessor->deliveryReport($kafka, $message);
        }
    }
}
