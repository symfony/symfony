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
        $serialized = serialize($message);
        $this->assertEquals('C:33:"Symfony\Component\Mime\RawMessage":23:{a:1:{i:0;s:6:"string";}}', $serialized);
        $this->assertEquals('string', unserialize($serialized)->toString());
        // calling methods more than once work
        $serialized = serialize($message);
        $this->assertEquals('C:33:"Symfony\Component\Mime\RawMessage":23:{a:1:{i:0;s:6:"string";}}', $serialized);
        $this->assertEquals('string', unserialize($serialized)->toString());

        $message = new RawMessage(new \ArrayObject(['some', ' ', 'string']));
        $message = new RawMessage($message->toIterable());
        $serialized = serialize($message);
        $this->assertEquals('C:33:"Symfony\Component\Mime\RawMessage":29:{a:1:{i:0;s:11:"some string";}}', $serialized);
        $this->assertEquals('some string', unserialize($serialized)->toString());
        // calling methods more than once work
        $serialized = serialize($message);
        $this->assertEquals('C:33:"Symfony\Component\Mime\RawMessage":29:{a:1:{i:0;s:11:"some string";}}', $serialized);
        $this->assertEquals('some string', unserialize($serialized)->toString());
    }
}
