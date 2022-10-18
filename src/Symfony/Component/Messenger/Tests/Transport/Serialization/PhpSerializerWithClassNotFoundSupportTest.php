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
        $this->expectException(MessageDecodingFailedException::class);

        $serializer = $this->createPhpSerializer();

        $serializer->decode([
            'body' => 'O:13:"ReceivedSt0mp":0:{}',
        ]);
    }

    public function testDecodingFailsButCreateClassNotFound()
    {
        $serializer = $this->createPhpSerializer();

        $encodedEnvelope = $serializer->encode(new Envelope(new DummyMessage('Hello')));
        // Simulate a change in the code base
        $encodedEnvelope['body'] = str_replace('DummyMessage', 'OupsyMessage', $encodedEnvelope['body']);

        $envelope = $serializer->decode($encodedEnvelope);

        $lastMessageDecodingFailedStamp = $envelope->last(MessageDecodingFailedStamp::class);
        $this->assertInstanceOf(MessageDecodingFailedStamp::class, $lastMessageDecodingFailedStamp);
        $message = $envelope->getMessage();
        // The class does not exist, so we cannot use anything else. The only
        // purpose of this feature is to aim debugging (so dumping value)
        ob_start();
        var_dump($message);
        $content = ob_get_clean();
        // remove object ID
        $content = preg_replace('/#\d+/', '', $content);
        $expected = <<<EOT
            object(__PHP_Incomplete_Class) (2) {
              ["__PHP_Incomplete_Class_Name"]=>
              string(55) "Symfony\Component\Messenger\Tests\Fixtures\OupsyMessage"
              ["message":"Symfony\Component\Messenger\Tests\Fixtures\OupsyMessage":private]=>
              string(5) "Hello"
            }

            EOT;
        $this->assertEquals($expected, $content);
    }

    protected function createPhpSerializer(): PhpSerializer
    {
        $serializer = new PhpSerializer();
        $serializer->acceptPhpIncompleteClass();

        return $serializer;
    }
}
