<?php

namespace Symfony\Component\Serializer\Tests\Normalizer;

use PHPUnit\Framework\TestCase;

use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;

use Symfony\Component\Serializer\Tests\Fixtures\Normalizer\EnumBackedDummy;
use Symfony\Component\Serializer\Tests\Fixtures\Normalizer\EnumPureDummy;

class BackedEnumNormalizerTest extends TestCase
{
    /**
     * @var BackedEnumNormalizer
     */
    private $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new BackedEnumNormalizer();
    }

    /**
     * @requires PHP >= 8.1
     */
    public function testSupportsNormalization()
    {
        $this->assertTrue($this->normalizer->supportsNormalization(EnumBackedDummy::Diamonds));
        $this->assertFalse($this->normalizer->supportsNormalization(EnumPureDummy::Diamonds));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    /**
     * @requires PHP < 8.1
     */
    public function testSupportsNormalizationEnumUnsupported()
    {
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    /**
     * @requires PHP >= 8.1
     */
    public function testNormalize()
    {
        $this->assertSame('D', $this->normalizer->normalize(EnumBackedDummy::Diamonds));
    }

    /**
     * @requires PHP >= 8.1
     */
    public function testSupportsDenormalization()
    {
        $this->assertTrue($this->normalizer->supportsDenormalization('D', EnumBackedDummy::class));
        $this->assertFalse($this->normalizer->supportsDenormalization('D', EnumPureDummy::class));
        $this->assertFalse($this->normalizer->supportsDenormalization('D', \stdClass::class));
    }

    /**
     * @requires PHP < 8.1
     */
    public function testSupportsDenormalizationEnumUnsupported()
    {
        $this->assertFalse($this->normalizer->supportsDenormalization('D', \stdClass::class));
    }

    /**
     * @requires PHP >= 8.1
     */
    public function testDenormalize()
    {
        $this->assertEquals(EnumBackedDummy::Diamonds, $this->normalizer->denormalize('D', EnumBackedDummy::class));
    }

    /**
     * @requires PHP >= 8.1
     */
    public function testDenormalizeThrowsException()
    {
        $this->expectException(NotNormalizableValueException::class);

        $this->normalizer->denormalize('Z', EnumBackedDummy::class);
    }
}
