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

use Symfony\Component\PropertyAccess\PropertyAccessorBuilder;
use Symfony\Component\PropertyAccess\Tests\Fixtures\TestSingularifyClass;

class PropertyAccessorBuilderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var PropertyAccessorBuilder
     */
    protected $builder;

    protected function setUp()
    {
        $this->builder = new PropertyAccessorBuilder();
    }

    protected function tearDown()
    {
        $this->builder = null;
    }

    public function testEnableMagicCall()
    {
        $this->assertSame($this->builder, $this->builder->enableMagicCall());
    }

    public function testDisableMagicCall()
    {
        $this->assertSame($this->builder, $this->builder->disableMagicCall());
    }

    public function testIsMagicCallEnable()
    {
        $this->assertFalse($this->builder->isMagicCallEnabled());
        $this->assertTrue($this->builder->enableMagicCall()->isMagicCallEnabled());
        $this->assertFalse($this->builder->disableMagicCall()->isMagicCallEnabled());
    }

    public function testGetPropertyAccessor()
    {
        $this->assertInstanceOf('Symfony\Component\PropertyAccess\PropertyAccessor', $this->builder->getPropertyAccessor());
        $this->assertInstanceOf('Symfony\Component\PropertyAccess\PropertyAccessor', $this->builder->enableMagicCall()->getPropertyAccessor());
    }

    public function testPropertySingularify()
    {
        $this->assertInstanceOf('Symfony\Component\PropertyAccess\Tests\Fixtures\TestSingularifyClass', $this->builder->setPropertySingularify(new TestSingularifyClass())->getPropertySingularify());
    }
}
