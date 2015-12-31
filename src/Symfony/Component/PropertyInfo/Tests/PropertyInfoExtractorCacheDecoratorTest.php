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

use Doctrine\Common\Cache\ArrayCache;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorCacheDecorator;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class PropertyInfoExtractorCacheDecoratorTest extends AbstractPropertyInfoExtractorTest
{
    public function setUp()
    {
        parent::setUp();

        $this->propertyInfo = new PropertyInfoExtractorCacheDecorator($this->propertyInfo, new ArrayCache());
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
}
