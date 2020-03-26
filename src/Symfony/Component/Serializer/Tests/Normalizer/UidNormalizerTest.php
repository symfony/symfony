<?php

namespace Symfony\Component\Serializer\Tests\Normalizer;

use PHPUnit\Framework\TestCase;
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

    public function testNormalize()
    {
        $this->assertEquals('9b7541de-6f87-11ea-ab3c-9da9a81562fc', $this->normalizer->normalize(Uuid::fromString('9b7541de-6f87-11ea-ab3c-9da9a81562fc')));
        $this->assertEquals('e576629b-ff34-3642-9c08-1f5219f0d45b', $this->normalizer->normalize(Uuid::fromString('e576629b-ff34-3642-9c08-1f5219f0d45b')));
        $this->assertEquals('4126dbc1-488e-4f6e-aadd-775dcbac482e', $this->normalizer->normalize(Uuid::fromString('4126dbc1-488e-4f6e-aadd-775dcbac482e')));
        $this->assertEquals('18cdf3d3-ea1b-5b23-a9c5-40abd0e2df22', $this->normalizer->normalize(Uuid::fromString('18cdf3d3-ea1b-5b23-a9c5-40abd0e2df22')));
        $this->assertEquals('1ea6ecef-eb9a-66fe-b62b-957b45f17e43', $this->normalizer->normalize(Uuid::fromString('1ea6ecef-eb9a-66fe-b62b-957b45f17e43')));
        $this->assertEquals('01E4BYF64YZ97MDV6RH0HAMN6X', $this->normalizer->normalize(Ulid::fromString('01E4BYF64YZ97MDV6RH0HAMN6X')));
    }

    public function testSupportsDenormalization()
    {
        $this->assertTrue($this->normalizer->supportsDenormalization('feb99b6e-6ece-11ea-bec2-957b45f17e43', UuidV1::class));
        $this->assertTrue($this->normalizer->supportsDenormalization('e576629b-ff34-3642-9c08-1f5219f0d45b', UuidV3::class));
        $this->assertTrue($this->normalizer->supportsDenormalization('4126dbc1-488e-4f6e-aadd-775dcbac482e', UuidV4::class));
        $this->assertTrue($this->normalizer->supportsDenormalization('18cdf3d3-ea1b-5b23-a9c5-40abd0e2df22', UuidV5::class));
        $this->assertTrue($this->normalizer->supportsDenormalization('1ea6ecef-eb9a-66fe-b62b-957b45f17e43', UuidV6::class));
        $this->assertTrue($this->normalizer->supportsDenormalization('feb99b6e-6ece-11ea-bec2-957b45f17e43', AbstractUid::class));
        $this->assertTrue($this->normalizer->supportsDenormalization('01E4BWRCYDA4Z7D57GJVRMJ6N1', Ulid::class));
        $this->assertFalse($this->normalizer->supportsDenormalization('foo', \stdClass::class));
    }

    public function testDenormalize()
    {
        $this->assertEquals(Uuid::fromString('9b7541de-6f87-11ea-ab3c-9da9a81562fc'), $this->normalizer->denormalize('9b7541de-6f87-11ea-ab3c-9da9a81562fc', UuidV1::class));
        $this->assertEquals(Uuid::fromString('e576629b-ff34-3642-9c08-1f5219f0d45b'), $this->normalizer->denormalize('e576629b-ff34-3642-9c08-1f5219f0d45b', UuidV3::class));
        $this->assertEquals(Uuid::fromString('4126dbc1-488e-4f6e-aadd-775dcbac482e'), $this->normalizer->denormalize('4126dbc1-488e-4f6e-aadd-775dcbac482e', UuidV4::class));
        $this->assertEquals(Uuid::fromString('18cdf3d3-ea1b-5b23-a9c5-40abd0e2df22'), $this->normalizer->denormalize('18cdf3d3-ea1b-5b23-a9c5-40abd0e2df22', UuidV5::class));
        $this->assertEquals(Uuid::fromString('1ea6ecef-eb9a-66fe-b62b-957b45f17e43'), $this->normalizer->denormalize('1ea6ecef-eb9a-66fe-b62b-957b45f17e43', UuidV6::class));
        $this->assertEquals(Uuid::fromString('1ea6ecef-eb9a-66fe-b62b-957b45f17e43'), $this->normalizer->denormalize('1ea6ecef-eb9a-66fe-b62b-957b45f17e43', AbstractUid::class));
        $this->assertEquals(new Ulid('01E4BWRCYDA4Z7D57GJVRMJ6N1'), $this->normalizer->denormalize('01E4BWRCYDA4Z7D57GJVRMJ6N1', Ulid::class));
    }
}
