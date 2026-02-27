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
use Symfony\Component\Serializer\Normalizer\UnwrappingDenormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Tests\Normalizer\Features\ObjectDummy;

/**
 * @author Eduard Bulava <bulavaeduard@gmail.com>
 */
class UnwrappingDenormalizerTest extends TestCase
{
    public function testSupportsNormalization()
    {
        $denormalizer = new UnwrappingDenormalizer();
        $denormalizer->setSerializer($this->createStub(Serializer::class));

        $this->assertTrue($denormalizer->supportsDenormalization([], 'stdClass', 'any', [UnwrappingDenormalizer::UNWRAP_PATH => '[baz][inner]']));
        $this->assertFalse($denormalizer->supportsDenormalization([], 'stdClass', 'any', [UnwrappingDenormalizer::UNWRAP_PATH => '[baz][inner]', 'unwrapped' => true]));
        $this->assertFalse($denormalizer->supportsDenormalization([], 'stdClass', 'any', []));
    }

    public function testDenormalize()
    {
        $expected = new ObjectDummy();
        $expected->setBaz(true);
        $expected->bar = 'bar';
        $expected->setFoo('foo');

        $serializer = $this->createMock(Serializer::class);
        $serializer->expects($this->exactly(1))
            ->method('denormalize')
            ->with(['foo' => 'foo', 'bar' => 'bar', 'baz' => true])
            ->willReturn($expected);
        $denormalizer = new UnwrappingDenormalizer();
        $denormalizer->setSerializer($serializer);

        $result = $denormalizer->denormalize(
            ['data' => ['foo' => 'foo', 'bar' => 'bar', 'baz' => true]],
            ObjectDummy::class,
            'any',
            [UnwrappingDenormalizer::UNWRAP_PATH => '[data]']
        );

        $this->assertEquals('foo', $result->getFoo());
        $this->assertEquals('bar', $result->bar);
        $this->assertTrue($result->isBaz());
    }

    public function testDenormalizeInvalidPath()
    {
        $serializer = $this->createMock(Serializer::class);
        $serializer->expects($this->exactly(1))
            ->method('denormalize')
            ->with(null)
            ->willReturn(new ObjectDummy());
        $denormalizer = new UnwrappingDenormalizer();
        $denormalizer->setSerializer($serializer);

        $obj = $denormalizer->denormalize(
            ['data' => ['foo' => 'foo', 'bar' => 'bar', 'baz' => true]],
            ObjectDummy::class,
            'any',
            [UnwrappingDenormalizer::UNWRAP_PATH => '[invalid]']
        );

        $this->assertNull($obj->getFoo());
        $this->assertNull($obj->bar);
        $this->assertNull($obj->isBaz());
    }
}
