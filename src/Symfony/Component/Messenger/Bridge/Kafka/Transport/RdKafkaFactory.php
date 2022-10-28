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
use RdKafka\Producer as KafkaProducer;

/**
 * @author Konstantin Scheumann <konstantin@konstantin.codes>
 */
class RdKafkaFactory
{
    public function createConsumer(Conf $conf): KafkaConsumer
    {
        return new KafkaConsumer($conf);
    }

    public function createProducer(Conf $conf): KafkaProducer
    {
        return new KafkaProducer($conf);
    }
}
