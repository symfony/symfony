<?php

namespace Symfony\Component\Serializer\Tests\Normalizer\Features;

use Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Tests\Fixtures\NotSerializedConstructorArgumentDummy;

trait ConstructorArgumentsTestTrait
{
    abstract protected function getDenormalizerForConstructArguments(): DenormalizerInterface;

    public function testDefaultConstructorArguments()
    {
        $data = [
            'foo' => 10,
        ];

        $denormalizer = $this->getDenormalizerForConstructArguments();

        $result = $denormalizer->denormalize($data, ConstructorArgumentsObject::class, 'json', [
            'default_constructor_arguments' => [
                ConstructorArgumentsObject::class => ['foo' => '', 'bar' => '', 'baz' => null],
            ],
        ]);

        $this->assertEquals(new ConstructorArgumentsObject(10, '', null), $result);
    }

    public function testMetadataAwareNameConvertorWithNotSerializedConstructorParameter()
    {
        $denormalizer = $this->getDenormalizerForConstructArguments();

        $obj = new NotSerializedConstructorArgumentDummy('buz');
        $obj->setBar('xyz');

        $this->assertEquals(
            $obj,
            $denormalizer->denormalize(['bar' => 'xyz'],
                NotSerializedConstructorArgumentDummy::class,
                null,
                ['default_constructor_arguments' => [
                    NotSerializedConstructorArgumentDummy::class => ['foo' => 'buz'],
                ]]
            )
        );
    }

    public function testConstructorWithMissingData()
    {
        $data = [
            'foo' => 10,
        ];

        $normalizer = $this->getDenormalizerForConstructArguments();

        $this->expectException(MissingConstructorArgumentsException::class);
        $this->expectExceptionMessage('Cannot create an instance of '.ConstructorArgumentsObject::class.' from serialized data because its constructor requires parameter "bar" to be present.');
        $normalizer->denormalize($data, ConstructorArgumentsObject::class);
    }
}
