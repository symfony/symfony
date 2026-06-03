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

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\MessageDecodingFailedStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

class PhpSerializerWithClassNotFoundSupportTest extends PhpSerializerTest
{
    public function testDecodingFailsWithBadClass()
    {
        $serializer = $this->createPhpSerializer();

        $envelope = $serializer->decode([
            'body' => 'O:13:"ReceivedSt0mp":0:{}',
        ]);

        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelope->getMessage());
    }

    public function testDecodingFailsButCreateClassNotFound()
    {
        $serializer = $this->createPhpSerializer();

        $encodedEnvelope = $serializer->encode(new Envelope(new DummyMessage('Hello')));
        // Simulate a change in the code base
        $encodedEnvelope['body'] = str_replace('DummyMessage', 'OupsyMessage', $encodedEnvelope['body']);

        $envelope = $serializer->decode($encodedEnvelope);

        $this->assertInstanceOf(\__PHP_Incomplete_Class::class, $envelope->getMessage());
        $this->assertNotNull($envelope->last(MessageDecodingFailedStamp::class));
    }

    protected function createPhpSerializer(): PhpSerializer
    {
        $serializer = new PhpSerializer();
        $serializer->acceptPhpIncompleteClass();

        return $serializer;
    }
}
