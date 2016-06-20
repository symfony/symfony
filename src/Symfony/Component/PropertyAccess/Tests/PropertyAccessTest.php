<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Tests;

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Psr\Cache\CacheItemPoolInterface;

/**
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
final class PropertyAccessTest extends \PHPUnit_Framework_TestCase
{
    public function testCreatePropertyAccessor()
    {
        $this->assertInstanceOf(PropertyAccessor::class, PropertyAccess::createPropertyAccessor());

        // magicCall enabling
        $this->assertInstanceOf(PropertyAccessor::class, PropertyAccess::createPropertyAccessor(true));

        // throwExceptionOnInvalidIndex enabling
        $this->assertInstanceOf(PropertyAccessor::class, PropertyAccess::createPropertyAccessor(false, true));

        // cacheItemPool enabling
        $this->assertInstanceOf(PropertyAccessor::class, PropertyAccess::createPropertyAccessor(false, false, $this->getMock(CacheItemPoolInterface::class)));
    }
}
