<?php

namespace Symfony\Component\Serializer\Tests\Normalizer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\ObjectToPopulateTrait;
use Symfony\Component\Serializer\Tests\Fixtures\ProxyDummy;

class ObjectToPopulateTraitTest extends TestCase
{
    use ObjectToPopulateTrait;

    public function testExtractObjectToPopulateReturnsNullWhenKeyIsMissing()
    {
        $object = $this->extractObjectToPopulate(ProxyDummy::class, array());

        $this->assertNull($object);
    }

    public function testExtractObjectToPopulateReturnsNullWhenNonObjectIsProvided()
    {
        $object = $this->extractObjectToPopulate(ProxyDummy::class, array(
            'object_to_populate' => 'not an object',
        ));

        $this->assertNull($object);
    }

    public function testExtractObjectToPopulateReturnsNullWhenTheClassIsNotAnInstanceOfTheProvidedClass()
    {
        $object = $this->extractObjectToPopulate(ProxyDummy::class, array(
            'object_to_populate' => new \stdClass(),
        ));

        $this->assertNull($object);
    }

    public function testExtractObjectToPopulateReturnsObjectWhenEverythingChecksOut()
    {
        $expected = new ProxyDummy();
        $object = $this->extractObjectToPopulate(ProxyDummy::class, array(
            'object_to_populate' => $expected,
        ));

        $this->assertSame($expected, $object);
    }
}
