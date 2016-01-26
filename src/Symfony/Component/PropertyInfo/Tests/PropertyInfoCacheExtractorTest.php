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

use Symfony\Component\PropertyInfo\PropertyInfoCacheExtractor;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class PropertyInfoCacheExtractorTest extends AbstractPropertyInfoExtractorTest
{
    public function setUp()
    {
        parent::setUp();

        $this->propertyInfo = new PropertyInfoCacheExtractor($this->propertyInfo, new ArrayCac);
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

    public function testEscape()
    {
        $reflectionMethod = new \ReflectionMethod($this->propertyInfo, 'escape');
        $reflectionMethod->setAccessible(true);

        $this->assertSame('foo_bar', $this->propertyInfo->escape('foo_95bar'));
        $this->assertSame('foo_95bar', $this->propertyInfo->escape('foo_9595bar'));
        $this->assertSame('foo{bar}', $this->propertyInfo->escape('foo_123bar_125'));
        $this->assertSame('foo(bar)', $this->propertyInfo->escape('foo_40bar_41'));
        $this->assertSame('foo/bar', $this->propertyInfo->escape('foo_47bar'));
        $this->assertSame('foo\bar', $this->propertyInfo->escape('foo_92bar'));
        $this->assertSame('foo@bar', $this->propertyInfo->escape('foo_64bar'));
        $this->assertSame('foo:bar', $this->propertyInfo->escape('foo_58bar'));
    }
}
