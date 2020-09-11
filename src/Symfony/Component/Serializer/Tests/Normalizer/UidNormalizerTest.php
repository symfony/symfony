<?php

namespace Symfony\Component\Serializer\Tests\Normalizer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\UidNormalizer;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV1;
use Symfony\Component\Uid\UuidV3;
use Symfony\Component\Uid\UuidV4;
use Symfony\Component\Uid\UuidV5;
use Symfony\Component\Uid\UuidV6;

class UidNormalizerTest extends TestCase
{
    /**
     * @var UidNormalizer
     */
    private $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new UidNormalizer();
    }

    public function dataProvider()
    {
        return [
            ['9b7541de-6f87-11ea-ab3c-9da9a81562fc', UuidV1::class],
            ['e576629b-ff34-3642-9c08-1f5219f0d45b', UuidV3::class],
            ['4126dbc1-488e-4f6e-aadd-775dcbac482e', UuidV4::class],
            ['18cdf3d3-ea1b-5b23-a9c5-40abd0e2df22', UuidV5::class],
            ['1ea6ecef-eb9a-66fe-b62b-957b45f17e43', UuidV6::class],
            ['1ea6ecef-eb9a-66fe-b62b-957b45f17e43', AbstractUid::class],
            ['01E4BYF64YZ97MDV6RH0HAMN6X', Ulid::class],
        ];
    }

    public function testSupportsNormalization()
    {
        $this->assertTrue($this->normalizer->supportsNormalization(Uuid::v1()));
        $this->assertTrue($this->normalizer->supportsNormalization(Uuid::v3(Uuid::v1(), 'foo')));
        $this->assertTrue($this->normalizer->supportsNormalization(Uuid::v4()));
        $this->assertTrue($this->normalizer->supportsNormalization(Uuid::v5(Uuid::v1(), 'foo')));
        $this->assertTrue($this->normalizer->supportsNormalization(Uuid::v6()));
        $this->assertTrue($this->normalizer->supportsNormalization(new Ulid()));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    /**
     * @dataProvider dataProvider
     */
    public function testNormalize($uuidString, $class)
    {
        if (Ulid::class === $class) {
            $this->assertEquals($uuidString, $this->normalizer->normalize(Ulid::fromString($uuidString)));
        } else {
            $this->assertEquals($uuidString, $this->normalizer->normalize(Uuid::fromString($uuidString)));
        }
    }

    /**
     * @dataProvider dataProvider
     */
    public function testSupportsDenormalization($uuidString, $class)
    {
        $this->assertTrue($this->normalizer->supportsDenormalization($uuidString, $class));
    }

    public function testSupportsDenormalizationForNonUid()
    {
        $this->assertFalse($this->normalizer->supportsDenormalization('foo', \stdClass::class));
    }

    /**
     * @dataProvider dataProvider
     */
    public function testDenormalize($uuidString, $class)
    {
        if (Ulid::class === $class) {
            $this->assertEquals(new Ulid($uuidString), $this->normalizer->denormalize($uuidString, $class));
        } else {
            $this->assertEquals(Uuid::fromString($uuidString), $this->normalizer->denormalize($uuidString, $class));
        }
    }
}
