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
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
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
        $collector = new MessengerDataCollector();
        $message = new DummyMessage('dummy message');

        $next = $this->createPartialMock(\stdClass::class, array('__invoke'));
        $next->expects($this->once())->method('__invoke')->with($message)->willReturn($returnedValue);

        $this->assertSame($returnedValue, $collector->handle($message, $next));

        $messages = $collector->getMessages();
        $this->assertCount(1, $messages);

        $this->assertDumpMatchesFormat($expected, $messages[0]);
    }

    public function getHandleTestData()
    {
        $messageDump = <<<DUMP
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
array:2 [
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
array:2 [
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
array:2 [
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
        $collector = new MessengerDataCollector();
        $message = new DummyMessage('dummy message');

        $expectedException = new \RuntimeException('foo');
        $next = $this->createPartialMock(\stdClass::class, array('__invoke'));
        $next->expects($this->once())->method('__invoke')->with($message)->willThrowException($expectedException);

        try {
            $collector->handle($message, $next);
        } catch (\Throwable $actualException) {
            $this->assertSame($expectedException, $actualException);
        }

        $messages = $collector->getMessages();
        $this->assertCount(1, $messages);

        $this->assertDumpMatchesFormat(<<<DUMP
array:2 [
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
