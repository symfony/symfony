<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AutoMapper\Tests;

use Symfony\Component\AutoMapper\AutoMapperNormalizer;

class AutoMapperNormalizerTest extends AutoMapperBaseTest
{
    /** @var AutoMapperNormalizer */
    protected $normalizer;

    public function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new AutoMapperNormalizer($this->autoMapper);
    }

    public function testNormalize()
    {
        $object = new Fixtures\User(1, 'Jack', 37);
        $expected = ['id' => 1, 'name' => 'Jack', 'age' => 37];

        $normalized = $this->normalizer->normalize($object);
        self::assertIsArray($normalized);
        self::assertEquals($expected['id'], $normalized['id']);
        self::assertEquals($expected['name'], $normalized['name']);
        self::assertEquals($expected['age'], $normalized['age']);
    }

    public function testDenormalize()
    {
        $source = ['id' => 1, 'name' => 'Jack', 'age' => 37];

        /** @var Fixtures\User $denormalized */
        $denormalized = $this->normalizer->denormalize($source, Fixtures\User::class);
        self::assertInstanceOf(Fixtures\User::class, $denormalized);
        self::assertEquals($source['id'], $denormalized->getId());
        self::assertEquals($source['name'], $denormalized->name);
        self::assertEquals($source['age'], $denormalized->age);
    }

    public function testSupportsNormalization()
    {
        self::assertFalse($this->normalizer->supportsNormalization(['foo']));
        self::assertFalse($this->normalizer->supportsNormalization('{"foo":1}'));

        $object = new Fixtures\User(1, 'Jack', 37);
        self::assertTrue($this->normalizer->supportsNormalization($object));

        $stdClass = new \stdClass();
        $stdClass->id = 1;
        $stdClass->name = 'Jack';
        $stdClass->age = 37;
        self::assertFalse($this->normalizer->supportsNormalization($stdClass));
    }

    public function testSupportsDenormalization()
    {
        self::assertTrue($this->normalizer->supportsDenormalization(['foo' => 1], 'array'));
        self::assertTrue($this->normalizer->supportsDenormalization(['foo' => 1], 'json'));

        $user = ['id' => 1, 'name' => 'Jack', 'age' => 37];
        self::assertTrue($this->normalizer->supportsDenormalization($user, Fixtures\User::class));
        self::assertTrue($this->normalizer->supportsDenormalization($user, \stdClass::class));
    }
}
