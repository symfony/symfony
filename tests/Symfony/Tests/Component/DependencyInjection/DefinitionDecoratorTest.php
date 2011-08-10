<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Tests\Component\DependencyInjection;

use Symfony\Component\DependencyInjection\DefinitionDecorator;

class DefinitionDecoratorTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $def = new DefinitionDecorator('foo');

        $this->assertEquals('foo', $def->getParent());
        $this->assertEquals(array(), $def->getChanges());
    }

    /**
     * @dataProvider getPropertyTests
     */
    public function testSetProperty($property, $changeKey)
    {
        $def = new DefinitionDecorator('foo');

        $getter = 'get'.ucfirst($property);
        $setter = 'set'.ucfirst($property);

        $this->assertNull($def->$getter());
        $this->assertSame($def, $def->$setter('foo'));
        $this->assertEquals('foo', $def->$getter());
        $this->assertEquals(array($changeKey => true), $def->getChanges());
    }

    public function getPropertyTests()
    {
        return array(
            array('class', 'class'),
            array('factoryClass', 'factory_class'),
            array('factoryMethod', 'factory_method'),
            array('factoryService', 'factory_service'),
            array('configurator', 'configurator'),
            array('file', 'file'),
        );
    }

    public function testSetPublic()
    {
        $def = new DefinitionDecorator('foo');

        $this->assertTrue($def->isPublic());
        $this->assertSame($def, $def->setPublic(false));
        $this->assertFalse($def->isPublic());
        $this->assertEquals(array('public' => true), $def->getChanges());
    }

    public function testSetArgument()
    {
        $def = new DefinitionDecorator('foo');

        $this->assertEquals(array(), $def->getArguments());
        $this->assertSame($def, $def->replaceArgument(0, 'foo'));
        $this->assertEquals(array('index_0' => 'foo'), $def->getArguments());
    }
}
