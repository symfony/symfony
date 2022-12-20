<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Extractor;

use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\ConstructorExtractor;
use Symfony\Component\PropertyInfo\Tests\Fixtures\DummyExtractor;
use Symfony\Component\PropertyInfo\Type;

/**
 * @author Dmitrii Poddubnyi <dpoddubny@gmail.com>
 */
class ConstructorExtractorTest extends TestCase
{
    /**
     * @var ConstructorExtractor
     */
    private $extractor;

    protected function setUp(): void
    {
        $this->extractor = new ConstructorExtractor([new DummyExtractor()]);
    }

    public function testInstanceOf()
    {
        self::assertInstanceOf(\Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface::class, $this->extractor);
    }

    public function testGetTypes()
    {
        self::assertEquals([new Type(Type::BUILTIN_TYPE_STRING)], $this->extractor->getTypes('Foo', 'bar', []));
    }

    public function testGetTypesIfNoExtractors()
    {
        $extractor = new ConstructorExtractor([]);
        self::assertNull($extractor->getTypes('Foo', 'bar', []));
    }
}
