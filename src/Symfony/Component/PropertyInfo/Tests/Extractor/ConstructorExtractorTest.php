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
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\PropertyInfo\Extractor\ConstructorExtractor;
use Symfony\Component\PropertyInfo\Tests\Fixtures\DummyExtractor;
use Symfony\Component\PropertyInfo\Type as LegacyType;
use Symfony\Component\TypeInfo\Type;

/**
 * @author Dmitrii Poddubnyi <dpoddubny@gmail.com>
 */
class ConstructorExtractorTest extends TestCase
{
    use ExpectDeprecationTrait;

    private ConstructorExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new ConstructorExtractor([new DummyExtractor()]);
    }

    public function testInstanceOf()
    {
        $this->assertInstanceOf(\Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface::class, $this->extractor);
    }

    public function testGetType()
    {
        $this->assertEquals(Type::string(), $this->extractor->getType('Foo', 'bar', []));
    }

    public function testGetTypeIfNoExtractors()
    {
        $extractor = new ConstructorExtractor([]);
        $this->assertNull($extractor->getType('Foo', 'bar', []));
    }

    /**
     * @group legacy
     */
    public function testGetTypes()
    {
        $this->expectDeprecation('Since symfony/property-info 7.3: The "Symfony\Component\PropertyInfo\Extractor\ConstructorExtractor::getTypes()" method is deprecated, use "Symfony\Component\PropertyInfo\Extractor\ConstructorExtractor::getType()" instead.');

        $this->assertEquals([new LegacyType(LegacyType::BUILTIN_TYPE_STRING)], $this->extractor->getTypes('Foo', 'bar', []));
    }

    /**
     * @group legacy
     */
    public function testGetTypesIfNoExtractors()
    {
        $this->expectDeprecation('Since symfony/property-info 7.3: The "Symfony\Component\PropertyInfo\Extractor\ConstructorExtractor::getTypes()" method is deprecated, use "Symfony\Component\PropertyInfo\Extractor\ConstructorExtractor::getType()" instead.');

        $extractor = new ConstructorExtractor([]);
        $this->assertNull($extractor->getTypes('Foo', 'bar', []));
    }
}
