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
class UnwrappinDenormalizerTest extends TestCase
{
    private $denormalizer;

    private $serializer;

    protected function setUp(): void
    {
        $this->serializer = $this->createMock(Serializer::class);
        $this->denormalizer = new UnwrappingDenormalizer();
        $this->denormalizer->setSerializer($this->serializer);
    }

    public function testSupportsNormalization()
    {
        $this->assertTrue($this->denormalizer->supportsDenormalization([], 'stdClass', 'any', [UnwrappingDenormalizer::UNWRAP_PATH => '[baz][inner]']));
        $this->assertFalse($this->denormalizer->supportsDenormalization([], 'stdClass', 'any', [UnwrappingDenormalizer::UNWRAP_PATH => '[baz][inner]', 'unwrapped' => true]));
        $this->assertFalse($this->denormalizer->supportsDenormalization([], 'stdClass', 'any', []));
    }

    public function testDenormalize()
    {
        $expected = new ObjectDummy();
        $expected->setBaz(true);
        $expected->bar = 'bar';
        $expected->setFoo('foo');

        $this->serializer->expects($this->exactly(1))
            ->method('denormalize')
            ->with(['foo' => 'foo', 'bar' => 'bar', 'baz' => true])
            ->willReturn($expected);

        $result = $this->denormalizer->denormalize(
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
        $this->serializer->expects($this->exactly(1))
            ->method('denormalize')
            ->with(null)
            ->willReturn(new ObjectDummy());

        $obj = $this->denormalizer->denormalize(
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
