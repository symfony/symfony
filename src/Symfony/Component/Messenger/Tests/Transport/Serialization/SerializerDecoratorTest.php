<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\Serialization;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerDecorator;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class SerializerDecoratorTest extends TestCase
{
    public function testDecodeJustCallsDecoratedSerializer()
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $decorator = new SerializerDecorator($serializer);
        $serializer->expects($this->once())->method('decode')->willReturn(new Envelope(new DummyMessage('Body')));

        $decorator->decode([
            'body' => 'x',
        ]);
    }

    public function testEncodedSkipsNonEncodableStamps()
    {
        $serializer = new PhpSerializer();
        $decorator = new SerializerDecorator($serializer);

        $envelope = new Envelope(new DummyMessage('Hello'), [
            new DummyPhpSerializerNonSendableStamp(),
        ]);

        $encoded = $decorator->encode($envelope);
        $this->assertStringNotContainsString('DummyPhpSerializerNonSendableStamp', $encoded['body']);
    }
}

class DummyPhpSerializerNonSendableStamp implements NonSendableStampInterface
{
}
