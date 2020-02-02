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
use Symfony\Component\AutoMapper\Transformer\ChainTransformerFactory;
use Symfony\Component\AutoMapper\Transformer\MultipleTransformer;
use Symfony\Component\AutoMapper\Transformer\MultipleTransformerFactory;
use Symfony\Component\PropertyInfo\Type;

class MultipleTransformerFactoryTest extends TestCase
{
    public function testGetTransformer()
    {
        $chainFactory = new ChainTransformerFactory();
        $factory = new MultipleTransformerFactory($chainFactory);

        $chainFactory->addTransformerFactory($factory);
        $chainFactory->addTransformerFactory(new BuiltinTransformerFactory());

        $mapperMetadata = $this->getMockBuilder(MapperMetadata::class)->disableOriginalConstructor()->getMock();

        $transformer = $factory->getTransformer([new Type('string'), new Type('int')], [], $mapperMetadata);

        self::assertNotNull($transformer);
        self::assertInstanceOf(MultipleTransformer::class, $transformer);

        $transformer = $factory->getTransformer([new Type('string'), new Type('object')], [], $mapperMetadata);

        self::assertNotNull($transformer);
        self::assertInstanceOf(BuiltinTransformer::class, $transformer);
    }

    public function testNoTransformerIfNoSubTransformer()
    {
        $chainFactory = new ChainTransformerFactory();
        $factory = new MultipleTransformerFactory($chainFactory);

        $mapperMetadata = $this->getMockBuilder(MapperMetadata::class)->disableOriginalConstructor()->getMock();

        $transformer = $factory->getTransformer([new Type('string'), new Type('int')], [], $mapperMetadata);

        self::assertNull($transformer);
    }

    public function testNoTransformer()
    {
        $chainFactory = new ChainTransformerFactory();
        $factory = new MultipleTransformerFactory($chainFactory);

        $chainFactory->addTransformerFactory($factory);
        $chainFactory->addTransformerFactory(new BuiltinTransformerFactory());

        $mapperMetadata = $this->getMockBuilder(MapperMetadata::class)->disableOriginalConstructor()->getMock();

        $transformer = $factory->getTransformer(null, null, $mapperMetadata);

        self::assertNull($transformer);

        $transformer = $factory->getTransformer([], null, $mapperMetadata);

        self::assertNull($transformer);

        $transformer = $factory->getTransformer([new Type('string')], null, $mapperMetadata);

        self::assertNull($transformer);
    }
}
