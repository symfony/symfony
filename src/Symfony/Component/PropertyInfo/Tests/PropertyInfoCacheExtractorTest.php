<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests;

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\PropertyInfo\PropertyInfoCacheExtractor;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class PropertyInfoCacheExtractorTest extends AbstractPropertyInfoExtractorTest
{
    protected function setUp()
    {
        parent::setUp();

        $this->propertyInfo = new PropertyInfoCacheExtractor($this->propertyInfo, new ArrayAdapter());
    }

    public function testCache()
    {
        $this->assertSame('short', $this->propertyInfo->getShortDescription('Foo', 'bar', array()));
        $this->assertSame('short', $this->propertyInfo->getShortDescription('Foo', 'bar', array()));
    }

    public function testNotSerializableContext()
    {
        $this->assertSame('short', $this->propertyInfo->getShortDescription('Foo', 'bar', array('foo' => function () {})));
    }

    /**
     * @dataProvider escapeDataProvider
     */
    public function testEscape($toEscape, $expected)
    {
        $reflectionMethod = new \ReflectionMethod($this->propertyInfo, 'escape');
        $reflectionMethod->setAccessible(true);

        $this->assertSame($expected, $reflectionMethod->invoke($this->propertyInfo, $toEscape));
    }

    public function escapeDataProvider()
    {
        return array(
            array('foo_bar', 'foo_95bar'),
            array('foo_95bar', 'foo_9595bar'),
            array('foo{bar}', 'foo_123bar_125'),
            array('foo(bar)', 'foo_40bar_41'),
            array('foo/bar', 'foo_47bar'),
            array('foo\bar', 'foo_92bar'),
            array('foo@bar', 'foo_64bar'),
            array('foo:bar', 'foo_58bar'),
        );
    }
}
