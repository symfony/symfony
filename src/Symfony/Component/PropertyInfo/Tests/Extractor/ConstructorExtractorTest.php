<?php

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

    protected function setUp()
    {
        $this->extractor = new ConstructorExtractor([new DummyExtractor()]);
    }

    public function testInstanceOf()
    {
        $this->assertInstanceOf('Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface', $this->extractor);
    }

    public function testGetTypes()
    {
        $this->assertEquals([new Type(Type::BUILTIN_TYPE_STRING)], $this->extractor->getTypes('Foo', 'bar', []));
    }

    public function testGetTypes_ifNoExtractors()
    {
        $extractor = new ConstructorExtractor([]);
        $this->assertNull($extractor->getTypes('Foo', 'bar', []));
    }
}
