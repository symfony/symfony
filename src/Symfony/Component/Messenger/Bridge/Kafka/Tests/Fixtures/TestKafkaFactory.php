<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Kafka\Tests\Fixtures;

use RdKafka\KafkaConsumer;
use RdKafka\Producer;
use Symfony\Component\Messenger\Bridge\Kafka\Transport\KafkaFactory;

class TestKafkaFactory extends KafkaFactory
{
    public function __construct(
        public KafkaConsumer $consumer,
        public Producer $producer,
    ) {
    }

    public function createConsumer(array $kafkaConfig): KafkaConsumer
    {
        return $this->consumer;
    }

    public function createProducer(array $kafkaConfig): Producer
    {
        return $this->producer;
    }
}
