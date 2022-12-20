<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\DataCollector;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\DataCollector\MessengerDataCollector;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\TraceableMessageBus;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Dumper\CliDumper;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class MessengerDataCollectorTest extends TestCase
{
    /** @var CliDumper */
    private $dumper;

    protected function setUp(): void
    {
        $this->dumper = new CliDumper();
        $this->dumper->setColors(false);
    }

    public function testHandle()
    {
        $message = new DummyMessage('dummy message');
        $envelope = new Envelope($message);

        $bus = self::createMock(MessageBusInterface::class);
        $bus->method('dispatch')->with($message)->willReturn($envelope);
        $bus = new TraceableMessageBus($bus);

        $collector = new MessengerDataCollector();
        $collector->registerBus('default', $bus);

        $bus->dispatch($message);

        $collector->lateCollect();

        $messages = $collector->getMessages();
        self::assertCount(1, $messages);

        $file = __FILE__;
        $expected = <<<DUMP
array:5 [
  "bus" => "default"
  "stamps" => []
  "stamps_after_dispatch" => []
  "message" => array:2 [
    "type" => "Symfony\Component\Messenger\Tests\Fixtures\DummyMessage"
    "value" => Symfony\Component\Messenger\Tests\Fixtures\DummyMessage %A
      -message: "dummy message"
    }
  ]
  "caller" => array:3 [
    "name" => "MessengerDataCollectorTest.php"
    "file" => "$file"
    "line" => %d
  ]
]
DUMP;

        self::assertStringMatchesFormat($expected, $this->getDataAsString($messages[0]));
    }

    public function testHandleWithException()
    {
        $message = new DummyMessage('dummy message');

        $bus = self::createMock(MessageBusInterface::class);
        $bus->method('dispatch')->with($message)->willThrowException(new \RuntimeException('foo'));
        $bus = new TraceableMessageBus($bus);

        $collector = new MessengerDataCollector();
        $collector->registerBus('default', $bus);

        try {
            $line = __LINE__ + 1;
            $bus->dispatch($message);
        } catch (\Throwable $e) {
            // Ignore.
        }

        $collector->lateCollect();

        $messages = $collector->getMessages();
        self::assertCount(1, $messages);

        $file = __FILE__;
        self::assertStringMatchesFormat(<<<DUMP
array:6 [
  "bus" => "default"
  "stamps" => []
  "stamps_after_dispatch" => []
  "message" => array:2 [
    "type" => "Symfony\Component\Messenger\Tests\Fixtures\DummyMessage"
    "value" => Symfony\Component\Messenger\Tests\Fixtures\DummyMessage %A
      -message: "dummy message"
    }
  ]
  "caller" => array:3 [
    "name" => "MessengerDataCollectorTest.php"
    "file" => "$file"
    "line" => $line
  ]
  "exception" => array:2 [
    "type" => "RuntimeException"
    "value" => RuntimeException %A
  ]
]
DUMP, $this->getDataAsString($messages[0]));
    }

    public function testKeepsOrderedDispatchCalls()
    {
        $firstBus = self::createMock(MessageBusInterface::class);
        $firstBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));
        $firstBus = new TraceableMessageBus($firstBus);

        $secondBus = self::createMock(MessageBusInterface::class);
        $secondBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));
        $secondBus = new TraceableMessageBus($secondBus);

        $collector = new MessengerDataCollector();
        $collector->registerBus('first bus', $firstBus);
        $collector->registerBus('second bus', $secondBus);

        $firstBus->dispatch(new DummyMessage('#1'));
        $secondBus->dispatch(new DummyMessage('#2'));
        $secondBus->dispatch(new DummyMessage('#3'));
        $firstBus->dispatch(new DummyMessage('#4'));
        $secondBus->dispatch(new DummyMessage('#5'));

        $collector->lateCollect();

        $messages = $collector->getMessages();
        self::assertCount(5, $messages);

        self::assertSame('#1', $messages[0]['message']['value']['message']);
        self::assertSame('first bus', $messages[0]['bus']);

        self::assertSame('#2', $messages[1]['message']['value']['message']);
        self::assertSame('second bus', $messages[1]['bus']);

        self::assertSame('#3', $messages[2]['message']['value']['message']);
        self::assertSame('second bus', $messages[2]['bus']);

        self::assertSame('#4', $messages[3]['message']['value']['message']);
        self::assertSame('first bus', $messages[3]['bus']);

        self::assertSame('#5', $messages[4]['message']['value']['message']);
        self::assertSame('second bus', $messages[4]['bus']);
    }

    private function getDataAsString(Data $data): string
    {
        return rtrim($this->dumper->dump($data, true));
    }
}
