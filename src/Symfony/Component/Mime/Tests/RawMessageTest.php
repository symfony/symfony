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
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Mime\RawMessage;

class RawMessageTest extends TestCase
{
    use ExpectDeprecationTrait;

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
     *
     * @group legacy
     */
    public function testToIterableLegacy(mixed $messageParameter, bool $supportReuse)
    {
        $message = new RawMessage($messageParameter);
        $this->assertEquals('some string', implode('', iterator_to_array($message->toIterable())));

        if (!$supportReuse) {
            // in 7.0, the test with a generator will throw an exception
            $this->expectDeprecation('Since symfony/mime 6.4: Sending an email with a closed generator is deprecated and will throw in 7.0.');
            $this->assertEquals('some string', implode('', iterator_to_array($message->toIterable())));
        }
    }

    public static function provideMessages(): array
    {
        return [
            'string' => ['some string', true],
            'traversable' => [new \ArrayObject(['some', ' ', 'string']), true],
            'array' => [['some', ' ', 'string'], true],
            'generator' => [(function () { yield 'some'; yield ' '; yield 'string'; })(), false],
        ];
    }
}
