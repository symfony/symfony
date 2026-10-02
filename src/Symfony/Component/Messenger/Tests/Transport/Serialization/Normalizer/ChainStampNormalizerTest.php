<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\Serialization\Normalizer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ChainStamp;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\HandlerArgumentsStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\SecondMessage;
use Symfony\Component\Messenger\Transport\Serialization\Normalizer\ChainStampNormalizer;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer as SymfonySerializer;

class ChainStampNormalizerTest extends TestCase
{
    private const CONTEXT = [Serializer::MESSENGER_SERIALIZATION_CONTEXT => true];

    private ChainStampNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new ChainStampNormalizer();
        new SymfonySerializer([$this->normalizer, new ArrayDenormalizer(), new ObjectNormalizer()]);
    }

    public function testSupportsTheChainStampOfMessengerOnly()
    {
        $stamp = new ChainStamp(new SecondMessage());

        $this->assertTrue($this->normalizer->supportsNormalization($stamp, 'json', self::CONTEXT));
        $this->assertFalse($this->normalizer->supportsNormalization($stamp, 'json'));
        $this->assertFalse($this->normalizer->supportsNormalization(new SecondMessage(), 'json', self::CONTEXT));
        $this->assertTrue($this->normalizer->supportsDenormalization([], ChainStamp::class, 'json', self::CONTEXT));
        $this->assertFalse($this->normalizer->supportsDenormalization([], ChainStamp::class, 'json'));
        $this->assertFalse($this->normalizer->supportsDenormalization([], SecondMessage::class, 'json', self::CONTEXT));
    }

    public function testEachMessageIsNormalizedWithItsClassAndTheSendableStampsOfItsEnvelope()
    {
        $stamp = (new \ReflectionClass(ChainStamp::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(ChainStamp::class, 'messages'))->setValue($stamp, [new SecondMessage(), new Envelope(new DummyMessage('third'), [new DelayStamp(1000), new HandlerArgumentsStamp(['local'])])]);

        $this->assertSame(['messages' => [
            ['type' => SecondMessage::class, 'message' => [], 'stamps' => []],
            ['type' => DummyMessage::class, 'message' => ['message' => 'third'], 'stamps' => [['type' => DelayStamp::class, 'stamp' => ['delay' => 1000]]]],
        ]], $this->normalizer->normalize($stamp, 'json', self::CONTEXT));
    }

    public function testDenormalizingFailsWithoutMessages()
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage(\sprintf('The data is not a valid "%s" representation.', ChainStamp::class));

        $this->normalizer->denormalize(['messages' => [['message' => []]]], ChainStamp::class, 'json', self::CONTEXT);
    }

    public function testDenormalizingFailsWithAMessageTypeThatIsNotAClass()
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage(\sprintf('The data is not a valid "%s" representation.', ChainStamp::class));

        $this->normalizer->denormalize(['messages' => [['type' => 'NotAClass', 'message' => []]]], ChainStamp::class, 'json', self::CONTEXT);
    }
}
