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
use Symfony\Component\Mime\RawMessage;

class RawMessageTest extends TestCase
{
    public function testToString()
    {
        $message = new RawMessage('string');
        $this->assertEquals('string', $message->toString());
        $this->assertEquals('string', implode('', iterator_to_array($message->toIterable())));
        // calling methods more than once work
        $this->assertEquals('string', $message->toString());
        $this->assertEquals('string', implode('', iterator_to_array($message->toIterable())));

        $message = new RawMessage(new \ArrayObject(['some', ' ', 'string']));
        $this->assertEquals('some string', $message->toString());
        $this->assertEquals('some string', implode('', iterator_to_array($message->toIterable())));
        // calling methods more than once work
        $this->assertEquals('some string', $message->toString());
        $this->assertEquals('some string', implode('', iterator_to_array($message->toIterable())));
    }

    public function testSerialization()
    {
        $message = new RawMessage('string');
        $this->assertEquals('string', unserialize(serialize($message))->toString());
        // calling methods more than once work
        $this->assertEquals('string', unserialize(serialize($message))->toString());

        $message = new RawMessage(new \ArrayObject(['some', ' ', 'string']));
        $message = new RawMessage($message->toIterable());
        $this->assertEquals('some string', unserialize(serialize($message))->toString());
        // calling methods more than once work
        $this->assertEquals('some string', unserialize(serialize($message))->toString());
    }
}
