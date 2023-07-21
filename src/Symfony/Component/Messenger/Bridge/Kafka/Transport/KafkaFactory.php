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

/**
 * @see https://arnaud.le-blanc.net/php-rdkafka-doc/phpdoc/class.rdkafka-conf.html for more information on callback parameters.
 */
class KafkaFactory
{
    public function __construct(
        private readonly mixed $logCb = null,
        private readonly mixed $errorCb = null,
        private readonly mixed $rebalanceCb = null,
        private readonly mixed $deliveryReportMessageCb = null,
        private readonly mixed $offsetCommitCb = null,
        private readonly mixed $statsCb = null,
        private readonly mixed $consumeCb = null,
    ) {
    }

    /** @psalm-param array<string, bool|float|int|string|array<string>> $kafkaConfig */
    public function createConsumer(array $kafkaConfig): KafkaConsumer
    {
        $conf = $this->getBaseConf();

        if (\is_callable($this->rebalanceCb)) {
            $conf->setRebalanceCb($this->rebalanceCb);
        }

        if (\is_callable($this->consumeCb)) {
            $conf->setConsumeCb($this->consumeCb);
        }

        if (\is_callable($this->offsetCommitCb)) {
            $conf->setOffsetCommitCb($this->offsetCommitCb);
        }

        foreach ($kafkaConfig as $key => $value) {
            $conf->set($key, $value);
        }

        return new KafkaConsumer($conf);
    }

    /** @psalm-param array<string, bool|float|int|string|array<string>> $kafkaConfig */
    public function createProducer(array $kafkaConfig): Producer
    {
        $conf = $this->getBaseConf();

        if (\is_callable($this->deliveryReportMessageCb)) {
            $conf->setDrMsgCb($this->deliveryReportMessageCb);
        }

        foreach ($kafkaConfig as $key => $value) {
            $conf->set($key, $value);
        }

        return new Producer($conf);
    }

    private function getBaseConf(): Conf
    {
        $conf = new Conf();

        if (\is_callable($this->logCb)) {
            $conf->setLogCb($this->logCb);
        }

        if (\is_callable($this->errorCb)) {
            $conf->setErrorCb($this->errorCb);
        }

        if (\is_callable($this->statsCb)) {
            $conf->setStatsCb($this->statsCb);
        }

        return $conf;
    }
}
