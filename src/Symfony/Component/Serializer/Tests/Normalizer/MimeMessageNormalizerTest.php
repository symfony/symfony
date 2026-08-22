<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Normalizer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\AbstractPart;
use Symfony\Component\Mime\Part\TextPart;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\MimeMessageNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Symfony\Component\Serializer\Serializer;

class MimeMessageNormalizerTestForeignClass
{
    public string $marker = '';
}

class MimeMessageNormalizerTest extends TestCase
{
    public function testDenormalizePartRoundTrip()
    {
        $serializer = $this->createMimeSerializer();

        $part = new TextPart('Text content');
        $part->getHeaders()->addHeader('foo', 'bar');

        $normalized = $serializer->normalize($part);
        $this->assertSame(TextPart::class, $normalized['class']);

        $this->assertEquals($part, $serializer->denormalize($normalized, AbstractPart::class));
    }

    public function testDenormalizeRejectsForeignClassInPart()
    {
        $serializer = $this->createMimeSerializer();

        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('Expected a subclass of "Symfony\Component\Mime\Part\AbstractPart", got "Symfony\Component\Serializer\Tests\Normalizer\MimeMessageNormalizerTestForeignClass".');

        $serializer->denormalize(['class' => MimeMessageNormalizerTestForeignClass::class, 'marker' => 'foo', 'headers' => []], AbstractPart::class);
    }

    public function testDenormalizeRejectsNonStringClassInPart()
    {
        $serializer = $this->createMimeSerializer();

        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('Expected a subclass of "Symfony\Component\Mime\Part\AbstractPart", got "array".');

        $serializer->denormalize(['class' => [], 'headers' => []], AbstractPart::class);
    }

    public function testNormalizeAddsTheMessageClass()
    {
        $serializer = $this->createMimeSerializer();

        $this->assertSame(Email::class, $serializer->normalize(new Email())['class']);
        $this->assertSame(RawMessage::class, $serializer->normalize(new RawMessage('Raw content'))['class']);
    }

    public function testDenormalizeMessageFromRawMessageType()
    {
        $serializer = $this->createMimeSerializer();

        // no addresses: egulias/email-validator is not installed on low-deps
        $email = (new Email())->subject('Text subject')->text('Text content');

        $denormalized = $serializer->denormalize($serializer->normalize($email), RawMessage::class);

        $this->assertInstanceOf(Email::class, $denormalized);
        $this->assertSame('Text content', $denormalized->getTextBody());
        $this->assertEquals($email->getHeaders(), $denormalized->getHeaders());
    }

    public function testDenormalizeRawMessageFromRawMessageType()
    {
        $serializer = $this->createMimeSerializer();

        $denormalized = $serializer->denormalize($serializer->normalize(new RawMessage('Raw content')), RawMessage::class);

        $this->assertSame(RawMessage::class, $denormalized::class);
        $this->assertSame('Raw content', $denormalized->toString());
    }

    public function testDenormalizeRejectsForeignClassInMessage()
    {
        $serializer = $this->createMimeSerializer();

        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('Expected a subclass of "Symfony\Component\Mime\RawMessage", got "Symfony\Component\Serializer\Tests\Normalizer\MimeMessageNormalizerTestForeignClass".');

        $serializer->denormalize(['class' => MimeMessageNormalizerTestForeignClass::class, 'marker' => 'foo'], RawMessage::class);
    }

    public function testDenormalizeRejectsNonArrayDataInPart()
    {
        $serializer = $this->createMimeSerializer();

        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('Expected a subclass of "Symfony\Component\Mime\Part\AbstractPart", got "null".');

        $serializer->denormalize('a string', AbstractPart::class);
    }

    public function testDenormalizeNonArrayDataAsMessage()
    {
        $serializer = $this->createMimeSerializer();

        $message = $serializer->denormalize('a string', RawMessage::class);
        $this->assertSame(RawMessage::class, $message::class);
        $this->assertSame('a string', $message->toString());
        $this->assertInstanceOf(Email::class, $serializer->denormalize('a string', Email::class));
    }

    private function createMimeSerializer(): Serializer
    {
        $extractor = new PhpDocExtractor();
        $propertyNormalizer = new PropertyNormalizer(null, null, $extractor);

        return new Serializer([
            new ArrayDenormalizer(),
            new MimeMessageNormalizer($propertyNormalizer),
            new ObjectNormalizer(null, null, null, $extractor),
            $propertyNormalizer,
        ], [new JsonEncoder()]);
    }
}
