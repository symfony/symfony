<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Stamp;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer as SymfonySerializer;

class TransportNamesStampTest extends TestCase
{
    public function testGetSenders()
    {
        $configuredSenders = ['first_transport', 'second_transport', 'other_transport'];
        $stamp = new TransportNamesStamp($configuredSenders);
        $stampSenders = $stamp->getTransportNames();
        $this->assertEquals(\count($configuredSenders), \count($stampSenders));

        foreach ($configuredSenders as $key => $sender) {
            $this->assertSame($sender, $stampSenders[$key]);
        }
    }

    public function testDeserialization()
    {
        $stamp = new TransportNamesStamp(['foo']);
        $serializer = new Serializer(
            new SymfonySerializer([
                new ArrayDenormalizer(),
                new ObjectNormalizer(),
            ], [new JsonEncoder()])
        );

        $deserializedEnvelope = $serializer->decode($serializer->encode(new Envelope(new \stdClass(), [$stamp])));

        $deserializedStamp = $deserializedEnvelope->last(TransportNamesStamp::class);
        $this->assertInstanceOf(TransportNamesStamp::class, $deserializedStamp);
        $this->assertEquals($stamp, $deserializedStamp);
    }

    public function testGetIndividualSender()
    {
        $stamp = new TransportNamesStamp('first_transport');
        $stampSenders = $stamp->getTransportNames();

        $this->assertCount(1, $stampSenders);
        $this->assertSame('first_transport', $stampSenders[0]);
    }
}
