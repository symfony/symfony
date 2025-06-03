<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Tests\Caster;

use PHPUnit\Framework\TestCase;
use RdKafka\Conf;
use RdKafka\KafkaConsumer;
use RdKafka\Producer;
use RdKafka\TopicConf;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;

/**
 * @requires extension rdkafka
 *
 * @group integration
 */
class RdKafkaCasterTest extends TestCase
{
    use VarDumperTestTrait;

    private const TOPIC = 'test-topic';
    private const GROUP_ID = 'test-group-id';

    private bool $hasBroker = false;
    private string $broker;

    protected function setUp(): void
    {
        if (!$this->hasBroker && getenv('KAFKA_BROKER')) {
            $this->broker = getenv('KAFKA_BROKER');
            $this->hasBroker = true;
        }
    }

    public function testDumpConf()
    {
        $conf = new Conf();
        $conf->setErrorCb(function ($kafka, $err, $reason) {});
        $conf->setDrMsgCb(function () {});
        $conf->setRebalanceCb(function () {});

        // BC with earlier version of extension rdkafka
        foreach (['setLogCb', 'setOffsetCommitCb', 'setStatsCb', 'setConsumeCb'] as $method) {
            if (method_exists($conf, $method)) {
                $conf->{$method}(function () {});
            }
        }

        $expectedDump = <<<EODUMP
RdKafka\Conf {
  builtin.features: "gzip,snappy,ssl,sasl,regex,lz4,sasl_gssapi,sasl_plain,sasl_scram,plugins%S"
  client.id: "rdkafka"
%A
  dr_msg_cb: "0x%x"
%A
}
EODUMP;

        $this->assertDumpMatchesFormat($expectedDump, $conf);
    }

    public function testDumpProducer()
    {
        if (!$this->hasBroker) {
            $this->markTestSkipped('Test requires an active broker');
        }

        $producer = new Producer(new Conf());
        $producer->addBrokers($this->broker);

        $expectedDump = <<<EODUMP
RdKafka\Producer {
%Aout_q_len: %d
  orig_broker_id: 1001
  orig_broker_name: "$this->broker/1001"
  brokers: RdKafka\Metadata\Collection {
    +0: RdKafka\Metadata\Broker {
      id: 1001
      host: "%s"
      port: %d
    }
  }
  topics: RdKafka\Metadata\Collection {
    +0: RdKafka\Metadata\Topic {
      name: "%s"
      partitions: RdKafka\Metadata\Collection {
        +0: RdKafka\Metadata\Partition {
          id: 0
          err: 0
          leader: 1001
        }%A
      }
    }%A
  }
}
EODUMP;

        $this->assertDumpMatchesFormat($expectedDump, $producer);
    }

    public function testDumpTopicConf()
    {
        $topicConf = new TopicConf();
        $topicConf->set('auto.offset.reset', 'smallest');

        $expectedDump = <<<EODUMP
RdKafka\TopicConf {
  request.required.acks: "%i"
  request.timeout.ms: "%d"
  message.timeout.ms: "300000"
%A
  auto.commit.enable: "true"
  auto.commit.interval.ms: "60000"
  auto.offset.reset: "smallest"
  offset.store.path: "."
  offset.store.sync.interval.ms: "-1"
  offset.store.method: "broker"
  consume.callback.max.messages: "0"
}
EODUMP;

        $this->assertDumpMatchesFormat($expectedDump, $topicConf);
    }

    public function testDumpKafkaConsumer()
    {
        if (!$this->hasBroker) {
            $this->markTestSkipped('Test requires an active broker');
        }

        $conf = new Conf();
        $conf->set('metadata.broker.list', $this->broker);
        $conf->set('group.id', self::GROUP_ID);

        $consumer = new KafkaConsumer($conf);
        $consumer->subscribe([self::TOPIC]);

        $expectedDump = <<<EODUMP
RdKafka\KafkaConsumer {
%Asubscription: array:1 [
    0 => "test-topic"
  ]
  assignment: []
  orig_broker_id: %i
  orig_broker_name: "$this->broker/%s"
  brokers: RdKafka\Metadata\Collection {
    +0: RdKafka\Metadata\Broker {
      id: 1001
      host: "%s"
      port: %d
    }
  }
  topics: RdKafka\Metadata\Collection {
    +0: RdKafka\Metadata\Topic {
      name: "%s"
      partitions: RdKafka\Metadata\Collection {
        +0: RdKafka\Metadata\Partition {
          id: 0
          err: 0
          leader: 1001
        }%A
      }
    }%A
  }
}
EODUMP;

        $this->assertDumpMatchesFormat($expectedDump, $consumer);
    }

    public function testDumpProducerTopic()
    {
        $producer = new Producer(new Conf());
        $producer->addBrokers($this->broker);

        $topic = $producer->newTopic('test');
        $topic->produce(\RD_KAFKA_PARTITION_UA, 0, '{}');

        $expectedDump = <<<EODUMP
RdKafka\ProducerTopic {
  name: "test"
}
EODUMP;

        $this->assertDumpMatchesFormat($expectedDump, $topic);
    }

    public function testDumpMessage()
    {
        $conf = new Conf();
        $conf->set('metadata.broker.list', $this->broker);
        $conf->set('group.id', self::GROUP_ID);

        $consumer = new KafkaConsumer($conf);
        $consumer->subscribe([self::TOPIC]);

        // Force timeout
        $message = $consumer->consume(0);

        $expectedDump = <<<EODUMP
RdKafka\Message {
  +err: -185
  +topic_name: null
  +timestamp: null
  +partition: 0
  +payload: null
  +len: null
  +key: null
  +offset: 0%A
  errstr: "Local: Timed out"
}
EODUMP;

        $this->assertDumpMatchesFormat($expectedDump, $message);
    }
}
