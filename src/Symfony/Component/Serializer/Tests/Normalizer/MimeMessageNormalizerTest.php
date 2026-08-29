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
use Symfony\Component\Mime\Part\AbstractPart;
use Symfony\Component\Mime\Part\TextPart;
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
    public $marker = '';
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

    public function testDenormalizeRejectsNonArrayDataInPart()
    {
        $serializer = $this->createMimeSerializer();

        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('Expected a subclass of "Symfony\Component\Mime\Part\AbstractPart", got "null".');

        $serializer->denormalize('a string', AbstractPart::class);
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
