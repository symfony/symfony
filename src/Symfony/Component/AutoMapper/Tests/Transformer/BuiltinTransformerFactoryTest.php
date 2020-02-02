<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AutoMapper\Tests\Transformer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\AutoMapper\MapperMetadata;
use Symfony\Component\AutoMapper\Transformer\BuiltinTransformer;
use Symfony\Component\AutoMapper\Transformer\BuiltinTransformerFactory;
use Symfony\Component\PropertyInfo\Type;

class BuiltinTransformerFactoryTest extends TestCase
{
    public function testGetTransformer(): void
    {
        $factory = new BuiltinTransformerFactory();
        $mapperMetadata = $this->getMockBuilder(MapperMetadata::class)->disableOriginalConstructor()->getMock();

        $transformer = $factory->getTransformer([new Type('string')], [new Type('string')], $mapperMetadata);

        self::assertInstanceOf(BuiltinTransformer::class, $transformer);

        $transformer = $factory->getTransformer([new Type('bool')], [new Type('string')], $mapperMetadata);

        self::assertInstanceOf(BuiltinTransformer::class, $transformer);
    }

    public function testNoTransformer(): void
    {
        $factory = new BuiltinTransformerFactory();
        $mapperMetadata = $this->getMockBuilder(MapperMetadata::class)->disableOriginalConstructor()->getMock();

        $transformer = $factory->getTransformer([], [new Type('string')], $mapperMetadata);

        self::assertNull($transformer);

        $transformer = $factory->getTransformer(null, [new Type('string')], $mapperMetadata);

        self::assertNull($transformer);

        $transformer = $factory->getTransformer(['test'], [new Type('string')], $mapperMetadata);

        self::assertNull($transformer);

        $transformer = $factory->getTransformer([new Type('string'), new Type('string')], [new Type('string')], $mapperMetadata);

        self::assertNull($transformer);

        $transformer = $factory->getTransformer([new Type('array')], [new Type('string')], $mapperMetadata);

        self::assertNull($transformer);

        $transformer = $factory->getTransformer([new Type('object')], [new Type('string')], $mapperMetadata);

        self::assertNull($transformer);
    }
}
