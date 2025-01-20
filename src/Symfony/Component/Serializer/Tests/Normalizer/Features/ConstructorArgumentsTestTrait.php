<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
            'bar' => 10,
        ];

        $normalizer = $this->getDenormalizerForConstructArguments();
        try {
            $normalizer->denormalize($data, ConstructorArgumentsObject::class);
            self::fail(\sprintf('Failed asserting that exception of type "%s" is thrown.', MissingConstructorArgumentsException::class));
        } catch (MissingConstructorArgumentsException $e) {
            self::assertSame(ConstructorArgumentsObject::class, $e->getClass());
            self::assertSame(\sprintf('Cannot create an instance of "%s" from serialized data because its constructor requires the following parameters to be present : "$foo", "$baz".', ConstructorArgumentsObject::class), $e->getMessage());
            self::assertSame(['foo', 'baz'], $e->getMissingConstructorArguments());
        }
    }

    public function testExceptionsAreCollectedForConstructorWithMissingData()
    {
        $data = [
            'bar' => 10,
        ];

        $exceptions = [];

        $normalizer = $this->getDenormalizerForConstructArguments();
        $normalizer->denormalize($data, ConstructorArgumentsObject::class, null, [
            'not_normalizable_value_exceptions' => &$exceptions,
        ]);

        self::assertCount(2, $exceptions);
        self::assertSame('Failed to create object because the class misses the "foo" property.', $exceptions[0]->getMessage());
        self::assertSame('Failed to create object because the class misses the "baz" property.', $exceptions[1]->getMessage());
    }
}
