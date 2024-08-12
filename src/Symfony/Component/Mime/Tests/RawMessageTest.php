<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Exception\LogicException;
use Symfony\Component\Mime\RawMessage;

class RawMessageTest extends TestCase
{
    /**
     * @dataProvider provideMessages
     */
    public function testToString(mixed $messageParameter, bool $supportReuse)
    {
        $message = new RawMessage($messageParameter);
        $this->assertEquals('some string', $message->toString());
        $this->assertEquals('some string', implode('', iterator_to_array($message->toIterable())));

        if ($supportReuse) {
            // calling methods more than once work
            $this->assertEquals('some string', $message->toString());
            $this->assertEquals('some string', implode('', iterator_to_array($message->toIterable())));
        }
    }

    /**
     * @dataProvider provideMessages
     */
    public function testSerialization(mixed $messageParameter, bool $supportReuse)
    {
        $message = new RawMessage($messageParameter);
        $this->assertEquals('some string', unserialize(serialize($message))->toString());

        if ($supportReuse) {
            // calling methods more than once work
            $this->assertEquals('some string', unserialize(serialize($message))->toString());
        }
    }

    /**
     * @dataProvider provideMessages
     */
    public function testToIterable(mixed $messageParameter, bool $supportReuse)
    {
        $message = new RawMessage($messageParameter);
        $this->assertEquals('some string', implode('', iterator_to_array($message->toIterable())));

        if ($supportReuse) {
            // calling methods more than once work
            $this->assertEquals('some string', implode('', iterator_to_array($message->toIterable())));
        }
    }

    /**
     * @dataProvider provideMessages
     */
    public function testToIterableLegacy(mixed $messageParameter, bool $supportReuse)
    {
        $message = new RawMessage($messageParameter);
        $this->assertEquals('some string', implode('', iterator_to_array($message->toIterable())));

        if (!$supportReuse) {
            $this->expectException(LogicException::class);
            iterator_to_array($message->toIterable());
        }
    }

    public function testToIterableOnResourceRewindsAndYieldsLines()
    {
        $handle = \fopen('php://memory', 'r+');
        \fwrite($handle, "line1\nline2\nline3\n");

        $message = new RawMessage($handle);
        $this->assertSame("line1\nline2\nline3\n", implode('', iterator_to_array($message->toIterable())));
    }

    public function testDestructClosesResource()
    {
        $handle = fopen('php://memory', 'r+');

        $message = new RawMessage($handle);
        unset($message);

        $this->assertIsClosedResource($handle);
    }

    public static function provideMessages(): array
    {
        return [
            'string' => ['some string', true],
            'traversable' => [new \ArrayObject(['some', ' ', 'string']), true],
            'array' => [['some', ' ', 'string'], true],
            'generator' => [(function () {
                yield 'some';
                yield ' ';
                yield 'string';
            })(), false],
        ];
    }
}
