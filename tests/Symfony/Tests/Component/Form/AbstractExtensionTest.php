<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form;

use Symfony\Component\Form\AbstractExtension;
use Symfony\Tests\Component\Form\Fixtures\FooType;

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
        $this->assertTrue($loader->getType('foo') instanceof FooType);
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
