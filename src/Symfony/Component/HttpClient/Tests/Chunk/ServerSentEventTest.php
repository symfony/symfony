<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests\Chunk;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Chunk\ServerSentEvent;
use Symfony\Component\HttpClient\Exception\JsonException;

/**
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
class ServerSentEventTest extends TestCase
{
    public function testParse()
    {
        $rawData = <<<STR
data: test
data:test
id: 12
event: testEvent

STR;

        $sse = new ServerSentEvent($rawData);
        $this->assertSame("test\ntest", $sse->getData());
        $this->assertSame('12', $sse->getId());
        $this->assertSame('testEvent', $sse->getType());
    }

    public function testParseValid()
    {
        $rawData = <<<STR
event: testEvent
data

STR;

        $sse = new ServerSentEvent($rawData);
        $this->assertSame('', $sse->getData());
        $this->assertSame('', $sse->getId());
        $this->assertSame('testEvent', $sse->getType());
    }

    public function testParseRetry()
    {
        $rawData = <<<STR
retry: 12
STR;
        $sse = new ServerSentEvent($rawData);
        $this->assertSame('', $sse->getData());
        $this->assertSame('', $sse->getId());
        $this->assertSame('message', $sse->getType());
        $this->assertSame(0.012, $sse->getRetry());
    }

    public function testParseNewLine()
    {
        $rawData = <<<STR


data: <tag>
data
data:   <foo />
data:
data: 
data: </tag>
STR;
        $sse = new ServerSentEvent($rawData);
        $this->assertSame("<tag>\n\n  <foo />\n\n\n</tag>", $sse->getData());
    }

    public function testGetArrayData()
    {
        $this->assertSame(['foo' => 'bar'], (new ServerSentEvent(<<<STR
id: 33
data: {"foo": "bar"}
STR
        ))->getArrayData());
    }

    public function testGetArrayDataWithNoContent()
    {
        $this->expectException(JsonException::class);
        $this->expectExceptionMessage('Server-Sent Event data is empty.');

        (new ServerSentEvent(''))->getArrayData();
    }

    public function testGetArrayDataWithInvalidJson()
    {
        $this->expectException(JsonException::class);
        $this->expectExceptionMessage('Decoding Server-Sent Event "33" failed: Syntax error');

        (new ServerSentEvent(<<<STR
id: 33
data: foobarccc
STR
        ))->getArrayData();
    }

    public function testGetArrayDataWithNonArrayJson()
    {
        $this->expectException(JsonException::class);
        $this->expectExceptionMessage('JSON content was expected to decode to an array, "string" returned in Server-Sent Event "33".');

        (new ServerSentEvent(<<<STR
id: 33
data: "ccc"
STR
        ))->getArrayData();
    }
}
