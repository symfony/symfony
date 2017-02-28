<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Argument;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Reference;

class ServiceLocatorArgumentTest extends TestCase
{
    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessage Values of a ServiceLocatorArgument must be Reference objects.
     */
    public function testThrowsOnNonReferenceValues()
    {
        new ServiceLocatorArgument(array('foo' => 'bar'));
    }

    public function testAcceptsReferencesOrNulls()
    {
        $locator = new ServiceLocatorArgument($values = array('foo' => new Reference('bar'), 'bar' => null));

        $this->assertSame($values, $locator->getValues());
    }
}
