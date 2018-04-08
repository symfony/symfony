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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\DataCollector\MessengerDataCollector;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\TraceableMessageBus;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class MessengerDataCollectorTest extends TestCase
{
    use VarDumperTestTrait;

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

        $collector->collect(Request::create('/'), new Response());

        $messages = $collector->getMessages();
        $this->assertCount(1, $messages);

        $this->assertDumpMatchesFormat($expected, $messages[0]);
    }

    public function getHandleTestData()
    {
        $messageDump = <<<DUMP
  "bus" => "default"
  "message" => array:2 [
    "type" => "Symfony\Component\Messenger\Tests\Fixtures\DummyMessage"
    "object" => Symfony\Component\VarDumper\Cloner\Data {%A
        %A+class: "Symfony\Component\Messenger\Tests\Fixtures\DummyMessage"%A
    }
  ]
DUMP;

        yield 'no returned value' => array(
            null,
            <<<DUMP
array:3 [
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
array:3 [
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
array:3 [
$messageDump
  "result" => array:2 [
    "type" => "array"
    "object" => Symfony\Component\VarDumper\Cloner\Data {%A
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

        $collector->collect(Request::create('/'), new Response());

        $messages = $collector->getMessages();
        $this->assertCount(1, $messages);

        $this->assertDumpMatchesFormat(<<<DUMP
array:3 [
  "bus" => "default"
  "message" => array:2 [
    "type" => "Symfony\Component\Messenger\Tests\Fixtures\DummyMessage"
    "object" => Symfony\Component\VarDumper\Cloner\Data {%A
        %A+class: "Symfony\Component\Messenger\Tests\Fixtures\DummyMessage"%A
    }
  ]
  "exception" => array:2 [
    "type" => "RuntimeException"
    "message" => "foo"
  ]
]    
DUMP
        , $messages[0]);
    }
}
