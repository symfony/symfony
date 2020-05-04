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
 * @group integration
 */
class RdKafkaCasterTest extends TestCase
{
    use VarDumperTestTrait;

    private const TOPIC = 'test-topic';
    private const GROUP_ID = 'test-group-id';

    private $hasBroker = false;
    private $broker;

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
  builtin.features: "gzip,snappy,ssl,sasl,regex,lz4,sasl_gssapi,sasl_plain,sasl_scram,plugins"
  client.id: "rdkafka"
  message.max.bytes: "1000000"
  message.copy.max.bytes: "65535"
  receive.message.max.bytes: "100000000"
  max.in.flight.requests.per.connection: "1000000"
  metadata.request.timeout.ms: "60000"
  topic.metadata.refresh.interval.ms: "300000"
  metadata.max.age.ms: "-1"
  topic.metadata.refresh.fast.interval.ms: "250"
  topic.metadata.refresh.fast.cnt: "10"
  topic.metadata.refresh.sparse: "true"
  debug: ""
  socket.timeout.ms: "60000"
  socket.blocking.max.ms: "1000"
  socket.send.buffer.bytes: "0"
  socket.receive.buffer.bytes: "0"
  socket.keepalive.enable: "false"
  socket.nagle.disable: "false"
  socket.max.fails: "%d"
  broker.address.ttl: "1000"
  broker.address.family: "any"
  reconnect.backoff.jitter.ms: "500"
  statistics.interval.ms: "0"
  enabled_events: "0"
  error_cb: "0x%x"
%A
  log_level: "6"
  log.queue: "%s"
  log.thread.name: "true"
  log.connection.close: "true"
  socket_cb: "0x%x"
  open_cb: "0x%x"
  internal.termination.signal: "0"
  api.version.request: "true"
  api.version.request.timeout.ms: "10000"
  api.version.fallback.ms: "1200000"
  broker.version.fallback: "0.9.0"
  security.protocol: "plaintext"
  sasl.mechanisms: "GSSAPI"
  sasl.kerberos.service.name: "kafka"
  sasl.kerberos.principal: "kafkaclient"
  sasl.kerberos.kinit.cmd: "kinit -S "%{sasl.kerberos.service.name}/%{broker.name}" -k -t "%{sasl.kerberos.keytab}" %{sasl.kerberos.principal}"
  sasl.kerberos.min.time.before.relogin: "60000"
  partition.assignment.strategy: "range,roundrobin"
  session.timeout.ms: "30000"
  heartbeat.interval.ms: "1000"
  group.protocol.type: "consumer"
  coordinator.query.interval.ms: "600000"
  enable.auto.commit: "true"
  auto.commit.interval.ms: "5000"
  enable.auto.offset.store: "true"
  queued.min.messages: "100000"
  queued.max.messages.kbytes: "1048576"
  fetch.wait.max.ms: "100"
%A
  fetch.min.bytes: "1"
  fetch.error.backoff.ms: "500"
  offset.store.method: "broker"
%A
  enable.partition.eof: "true"
  check.crcs: "false"
  queue.buffering.max.messages: "100000"
  queue.buffering.max.kbytes: "1048576"
  queue.buffering.max.ms: "0"
%A
  compression.codec: "none"
  batch.num.messages: "10000"
  delivery.report.only.error: "false"
  dr_msg_cb: "0x%x"
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
  -error_cb: null
  -dr_cb: null
  out_q_len: %d
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
  request.required.acks: "1"
  request.timeout.ms: "5000"
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
  -error_cb: null
  -rebalance_cb: null
  -dr_msg_cb: null
  subscription: array:1 [
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
        $topic->produce(RD_KAFKA_PARTITION_UA, 0, '{}');

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
