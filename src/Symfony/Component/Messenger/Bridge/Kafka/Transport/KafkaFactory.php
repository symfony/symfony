<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Kafka\Transport;

use RdKafka\Conf;
use RdKafka\KafkaConsumer;
use RdKafka\Producer;
use Symfony\Component\Messenger\Bridge\Kafka\Callback\CallbackManager;

class KafkaFactory
{
    public function __construct(
        private readonly CallbackManager $callbackManager,
    ) {
    }

    /**
     * @param array<string, string> $kafkaConfig
     */
    public function createConsumer(array $kafkaConfig): KafkaConsumer
    {
        $conf = $this->getBaseConf();
        $conf->setErrorCb([$this->callbackManager, 'consumerError']);
        $conf->setRebalanceCb([$this->callbackManager, 'rebalance']);
        $conf->setConsumeCb([$this->callbackManager, 'consume']);
        $conf->setOffsetCommitCb([$this->callbackManager, 'offsetCommit']);

        foreach ($kafkaConfig as $key => $value) {
            $conf->set($key, $value);
        }

        return new KafkaConsumer($conf);
    }

    /**
     * @param array<string, string> $kafkaConfig
     */
    public function createProducer(array $kafkaConfig): Producer
    {
        $conf = $this->getBaseConf();
        $conf->setErrorCb([$this->callbackManager, 'producerError']);
        $conf->setDrMsgCb([$this->callbackManager, 'deliveryReport']);

        foreach ($kafkaConfig as $key => $value) {
            $conf->set($key, $value);
        }

        return new Producer($conf);
    }

    private function getBaseConf(): Conf
    {
        $conf = new Conf();
        $conf->setLogCb([$this->callbackManager, 'log']);
        $conf->setStatsCb([$this->callbackManager, 'stats']);

        return $conf;
    }
}
