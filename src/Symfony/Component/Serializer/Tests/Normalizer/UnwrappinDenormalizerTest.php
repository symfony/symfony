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
        $this->serializer = self::createMock(Serializer::class);
        $this->denormalizer = new UnwrappingDenormalizer();
        $this->denormalizer->setSerializer($this->serializer);
    }

    public function testSupportsNormalization()
    {
        self::assertTrue($this->denormalizer->supportsDenormalization([], 'stdClass', 'any', [UnwrappingDenormalizer::UNWRAP_PATH => '[baz][inner]']));
        self::assertFalse($this->denormalizer->supportsDenormalization([], 'stdClass', 'any', [UnwrappingDenormalizer::UNWRAP_PATH => '[baz][inner]', 'unwrapped' => true]));
        self::assertFalse($this->denormalizer->supportsDenormalization([], 'stdClass', 'any', []));
    }

    public function testDenormalize()
    {
        $expected = new ObjectDummy();
        $expected->setBaz(true);
        $expected->bar = 'bar';
        $expected->setFoo('foo');

        $this->serializer->expects(self::exactly(1))
            ->method('denormalize')
            ->with(['foo' => 'foo', 'bar' => 'bar', 'baz' => true])
            ->willReturn($expected);

        $result = $this->denormalizer->denormalize(
            ['data' => ['foo' => 'foo', 'bar' => 'bar', 'baz' => true]],
            ObjectDummy::class,
            'any',
            [UnwrappingDenormalizer::UNWRAP_PATH => '[data]']
        );

        self::assertEquals('foo', $result->getFoo());
        self::assertEquals('bar', $result->bar);
        self::assertTrue($result->isBaz());
    }

    public function testDenormalizeInvalidPath()
    {
        $this->serializer->expects(self::exactly(1))
            ->method('denormalize')
            ->with(null)
            ->willReturn(new ObjectDummy());

        $obj = $this->denormalizer->denormalize(
            ['data' => ['foo' => 'foo', 'bar' => 'bar', 'baz' => true]],
            ObjectDummy::class,
            'any',
            [UnwrappingDenormalizer::UNWRAP_PATH => '[invalid]']
        );

        self::assertNull($obj->getFoo());
        self::assertNull($obj->bar);
        self::assertNull($obj->isBaz());
    }
}
