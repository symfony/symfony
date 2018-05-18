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

    protected function setUp()
    {
        $this->dumper = new CliDumper();
        $this->dumper->setColors(false);
    }

    /**
     * @dataProvider getHandleTestData
     */
    public function testHandle($returnedValue, $expected)
    {
        $message = new DummyMessage('dummy message');

        $bus = $this->getMockBuilder(MessageBusInterface::class)->getMock();
        $bus->method('dispatch')->with($message)->willReturn($returnedValue);
        $bus = new TraceableMessageBus($bus);

        $collector = new MessengerDataCollector();
        $collector->registerBus('default', $bus);

        $bus->dispatch($message);

        $collector->lateCollect();

        $messages = $collector->getMessages();
        $this->assertCount(1, $messages);

        $this->assertStringMatchesFormat($expected, $this->getDataAsString($messages[0]));
    }

    public function getHandleTestData()
    {
        $messageDump = <<<DUMP
  "bus" => "default"
  "envelopeItems" => null
  "message" => array:2 [
    "type" => "Symfony\Component\Messenger\Tests\Fixtures\DummyMessage"
    "value" => Symfony\Component\Messenger\Tests\Fixtures\DummyMessage %A
      -message: "dummy message"
    }
  ]
DUMP;

        yield 'no returned value' => array(
            null,
            <<<DUMP
array:4 [
$messageDump
  "result" => array:2 [
    "type" => "NULL"
    "value" => null
  ]
]
DUMP
        );

        yield 'scalar returned value' => array(
            'returned value',
            <<<DUMP
array:4 [
$messageDump
  "result" => array:2 [
    "type" => "string"
    "value" => "returned value"
  ]
]
DUMP
        );

        yield 'array returned value' => array(
            array('returned value'),
            <<<DUMP
array:4 [
$messageDump
  "result" => array:2 [
    "type" => "array"
    "value" => array:1 [
      0 => "returned value"
    ]
  ]
]
DUMP
        );
    }

    public function testHandleWithException()
    {
        $message = new DummyMessage('dummy message');

        $bus = $this->getMockBuilder(MessageBusInterface::class)->getMock();
        $bus->method('dispatch')->with($message)->will($this->throwException(new \RuntimeException('foo')));
        $bus = new TraceableMessageBus($bus);

        $collector = new MessengerDataCollector();
        $collector->registerBus('default', $bus);

        try {
            $bus->dispatch($message);
        } catch (\Throwable $e) {
            // Ignore.
        }

        $collector->lateCollect();

        $messages = $collector->getMessages();
        $this->assertCount(1, $messages);

        $this->assertStringMatchesFormat(<<<DUMP
array:4 [
  "bus" => "default"
  "envelopeItems" => null
  "message" => array:2 [
    "type" => "Symfony\Component\Messenger\Tests\Fixtures\DummyMessage"
    "value" => Symfony\Component\Messenger\Tests\Fixtures\DummyMessage %A
      -message: "dummy message"
    }
  ]
  "exception" => array:2 [
    "type" => "RuntimeException"
    "value" => RuntimeException %A
  ]
]
DUMP
        , $this->getDataAsString($messages[0]));
    }

    public function testKeepsOrderedDispatchCalls()
    {
        $firstBus = $this->getMockBuilder(MessageBusInterface::class)->getMock();
        $firstBus = new TraceableMessageBus($firstBus);

        $secondBus = $this->getMockBuilder(MessageBusInterface::class)->getMock();
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
        $this->assertCount(5, $messages);

        $this->assertSame('#1', $messages[0]['message']['value']['message']);
        $this->assertSame('first bus', $messages[0]['bus']);

        $this->assertSame('#2', $messages[1]['message']['value']['message']);
        $this->assertSame('second bus', $messages[1]['bus']);

        $this->assertSame('#3', $messages[2]['message']['value']['message']);
        $this->assertSame('second bus', $messages[2]['bus']);

        $this->assertSame('#4', $messages[3]['message']['value']['message']);
        $this->assertSame('first bus', $messages[3]['bus']);

        $this->assertSame('#5', $messages[4]['message']['value']['message']);
        $this->assertSame('second bus', $messages[4]['bus']);
    }

    private function getDataAsString(Data $data): string
    {
        return rtrim($this->dumper->dump($data, true));
    }
}
