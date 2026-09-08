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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ChainStamp;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\DispatchOnFailureStamp;
use Symfony\Component\Messenger\Stamp\FailedMessageStamp;
use Symfony\Component\Messenger\Stamp\HandlerArgumentsStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\SecondMessage;
use Symfony\Component\Messenger\Transport\Serialization\Normalizer\MessageStampNormalizer;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer as SymfonySerializer;

class MessageStampNormalizerTest extends TestCase
{
    private const CONTEXT = [Serializer::MESSENGER_SERIALIZATION_CONTEXT => true];

    private MessageStampNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new MessageStampNormalizer();
        new SymfonySerializer([$this->normalizer, new ArrayDenormalizer(), new ObjectNormalizer()]);
    }

    #[DataProvider('provideStamps')]
    public function testSupportsTheMessageStampsOfMessengerOnly(StampInterface $stamp)
    {
        $this->assertTrue($this->normalizer->supportsNormalization($stamp, 'json', self::CONTEXT));
        $this->assertFalse($this->normalizer->supportsNormalization($stamp, 'json'));
        $this->assertTrue($this->normalizer->supportsDenormalization([], $stamp::class, 'json', self::CONTEXT));
        $this->assertFalse($this->normalizer->supportsDenormalization([], $stamp::class, 'json'));
    }

    public static function provideStamps(): iterable
    {
        yield 'chain' => [new ChainStamp(new SecondMessage())];
        yield 'dispatch on failure' => [new DispatchOnFailureStamp(new SecondMessage())];
        yield 'failed message' => [new FailedMessageStamp(new SecondMessage())];
    }

    public function testOtherDataIsNotSupported()
    {
        $this->assertFalse($this->normalizer->supportsNormalization(new SecondMessage(), 'json', self::CONTEXT));
        $this->assertFalse($this->normalizer->supportsNormalization(new DelayStamp(1000), 'json', self::CONTEXT));
        $this->assertFalse($this->normalizer->supportsDenormalization([], SecondMessage::class, 'json', self::CONTEXT));
        $this->assertFalse($this->normalizer->supportsDenormalization([], DelayStamp::class, 'json', self::CONTEXT));
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

    public function testTheFailureMessageIsNormalizedWithItsClassAndTheSendableStampsOfItsEnvelope()
    {
        $stamp = (new \ReflectionClass(DispatchOnFailureStamp::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(DispatchOnFailureStamp::class, 'message'))->setValue($stamp, new Envelope(new DummyMessage('failure'), [new DelayStamp(1000), new HandlerArgumentsStamp(['local'])]));

        $this->assertSame(['type' => DummyMessage::class, 'message' => ['message' => 'failure'], 'stamps' => [['type' => DelayStamp::class, 'stamp' => ['delay' => 1000]]]], $this->normalizer->normalize($stamp, 'json', self::CONTEXT));
    }

    public function testTheFailedMessageIsNormalizedWithItsClass()
    {
        $this->assertSame(['type' => DummyMessage::class, 'message' => ['message' => 'failed'], 'stamps' => []], $this->normalizer->normalize(new FailedMessageStamp(new DummyMessage('failed')), 'json', self::CONTEXT));
    }

    #[DataProvider('provideDenormalizableStamps')]
    public function testDenormalizingRestoresTheStamp(StampInterface $stamp)
    {
        $this->assertEquals($stamp, $this->normalizer->denormalize($this->normalizer->normalize($stamp, 'json', self::CONTEXT), $stamp::class, 'json', self::CONTEXT));
    }

    public static function provideDenormalizableStamps(): iterable
    {
        yield 'chain' => [new ChainStamp(new SecondMessage(), new Envelope(new DummyMessage('third'), [new DelayStamp(1000)]))];
        yield 'dispatch on failure' => [new DispatchOnFailureStamp(new DummyMessage('failure'))];
        yield 'dispatch on failure with an envelope' => [new DispatchOnFailureStamp(new Envelope(new DummyMessage('failure'), [new DelayStamp(1000)]))];
        yield 'failed message' => [new FailedMessageStamp(new DummyMessage('failed'))];
    }

    #[DataProvider('provideInvalidData')]
    public function testDenormalizingFailsWithInvalidData(string $type, mixed $data)
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage(\sprintf('The data is not a valid "%s" representation.', $type));

        $this->normalizer->denormalize($data, $type, 'json', self::CONTEXT);
    }

    public static function provideInvalidData(): iterable
    {
        yield 'chain without messages' => [ChainStamp::class, ['messages' => [['message' => []]]]];
        yield 'chain with a message type that is not a class' => [ChainStamp::class, ['messages' => [['type' => 'NotAClass', 'message' => []]]]];
        yield 'failure message without type' => [DispatchOnFailureStamp::class, ['message' => []]];
        yield 'failure message type that is not a class' => [DispatchOnFailureStamp::class, ['type' => 'NotAClass', 'message' => []]];
        yield 'failure message with invalid stamps' => [DispatchOnFailureStamp::class, ['type' => DummyMessage::class, 'message' => ['message' => 'failure'], 'stamps' => [['stamp' => []]]]];
        yield 'failed message without message' => [FailedMessageStamp::class, ['type' => DummyMessage::class]];
        yield 'failed message that is not an array' => [FailedMessageStamp::class, 'failed'];
    }
}
