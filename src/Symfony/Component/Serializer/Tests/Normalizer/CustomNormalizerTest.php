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
use Symfony\Component\Serializer\Normalizer\CustomNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\Tests\Fixtures\ScalarDummy;

class CustomNormalizerTest extends TestCase
{
    /**
     * @var CustomNormalizer
     */
    private $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new CustomNormalizer();
        $this->normalizer->setSerializer(new Serializer());
    }

    public function testInterface()
    {
        self::assertInstanceOf(NormalizerInterface::class, $this->normalizer);
        self::assertInstanceOf(DenormalizerInterface::class, $this->normalizer);
        self::assertInstanceOf(SerializerAwareInterface::class, $this->normalizer);
    }

    public function testSerialize()
    {
        $obj = new ScalarDummy();
        $obj->foo = 'foo';
        $obj->xmlFoo = 'xml';
        self::assertEquals('foo', $this->normalizer->normalize($obj, 'json'));
        self::assertEquals('xml', $this->normalizer->normalize($obj, 'xml'));
    }

    public function testDeserialize()
    {
        $obj = $this->normalizer->denormalize('foo', \get_class(new ScalarDummy()), 'xml');
        self::assertEquals('foo', $obj->xmlFoo);
        self::assertNull($obj->foo);

        $obj = $this->normalizer->denormalize('foo', \get_class(new ScalarDummy()), 'json');
        self::assertEquals('foo', $obj->foo);
        self::assertNull($obj->xmlFoo);
    }

    public function testDenormalizeWithObjectToPopulateUsesProvidedObject()
    {
        $expected = new ScalarDummy();
        $obj = $this->normalizer->denormalize('foo', ScalarDummy::class, 'json', [
            'object_to_populate' => $expected,
        ]);

        self::assertSame($expected, $obj);
        self::assertEquals('foo', $obj->foo);
        self::assertNull($obj->xmlFoo);
    }

    public function testSupportsNormalization()
    {
        self::assertTrue($this->normalizer->supportsNormalization(new ScalarDummy()));
        self::assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    public function testSupportsDenormalization()
    {
        self::assertTrue($this->normalizer->supportsDenormalization([], 'Symfony\Component\Serializer\Tests\Fixtures\ScalarDummy'));
        self::assertFalse($this->normalizer->supportsDenormalization([], 'stdClass'));
        self::assertTrue($this->normalizer->supportsDenormalization([], 'Symfony\Component\Serializer\Tests\Fixtures\DenormalizableDummy'));
    }
}
