<?php

namespace Symfony\Component\Serializer\Tests\Normalizer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\ScalarNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

class ScalarNormalizerTest extends TestCase
{
    protected SerializerInterface&NormalizerInterface $serializer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createNormalizer();
    }


    protected function createNormalizer(): void
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $normalizer = new ObjectNormalizer($classMetadataFactory, new MetadataAwareNameConverter($classMetadataFactory));
        $scalarNormalizer = new ScalarNormalizer();
        $xmlEncoder = new XmlEncoder();
        $this->serializer = new Serializer([$normalizer, $scalarNormalizer], [$xmlEncoder]);
    }

    public function testBoolWithoutContext()
    {
        self::assertXmlStringEqualsXmlString('<?xml version="1.0"?>
<response><fooTrue>1</fooTrue><fooFalse>0</fooFalse></response>',
            $this->serializer->serialize(new ObjectWithBool(), 'xml'));
    }

    public function testBoolWithContext()
    {
        self::assertXmlStringEqualsXmlString('<?xml version="1.0"?>
<response><fooTrue>true</fooTrue><fooFalse>false</fooFalse></response>',
            $this->serializer->serialize(new ObjectWithBool(), 'xml', [
                ScalarNormalizer::TRUE_VALUE_KEY => 'true',
                ScalarNormalizer::FALSE_VALUE_KEY => 'false'
            ]));
    }
}


class ObjectWithBool
{
    public bool $fooTrue = true;
    public bool $fooFalse = false;
}
