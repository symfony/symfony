<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests;

use Symfony\Component\Form\AbstractExtension;
use Symfony\Component\Form\Tests\Fixtures\CustomOptionsResolver;
use Symfony\Component\Form\Tests\Fixtures\FooType;
use Symfony\Component\Form\Tests\Fixtures\FooTypeBarExtension;

class AbstractExtensionTest extends \PHPUnit_Framework_TestCase
{
    public function testHasType()
    {
        $loader = new ConcreteExtension();
        $this->assertTrue($loader->hasType('foo'));
        $this->assertFalse($loader->hasType('bar'));
    }

    public function testGetType()
    {
        $loader = new ConcreteExtension();
        $this->assertInstanceOf('Symfony\Component\Form\Tests\Fixtures\FooType', $loader->getType('foo'));
    }

    public function testCustomOptionsResolver()
    {
        $extension = new FooTypeBarExtension();
        $resolver = new CustomOptionsResolver();
        $extension->setDefaultOptions($resolver);
    }
}

class ConcreteExtension extends AbstractExtension
{
    protected function loadTypes()
    {
        return array(new FooType());
    }

    protected function loadTypeGuesser()
    {
    }
}
