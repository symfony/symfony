<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests;

use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\DefinitionDecorator;

class ChildDefinitionTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $def = new ChildDefinition('foo');

        $this->assertSame('foo', $def->getParent());
        $this->assertSame(array(), $def->getChanges());
    }

    /**
     * @dataProvider getPropertyTests
     */
    public function testSetProperty($property, $changeKey)
    {
        $def = new ChildDefinition('foo');

        $getter = 'get'.ucfirst($property);
        $setter = 'set'.ucfirst($property);

        $this->assertNull($def->$getter());
        $this->assertSame($def, $def->$setter('foo'));
        $this->assertSame('foo', $def->$getter());
        $this->assertSame(array($changeKey => true), $def->getChanges());
    }

    public function getPropertyTests()
    {
        return array(
            array('class', 'class'),
            array('factory', 'factory'),
            array('configurator', 'configurator'),
            array('file', 'file'),
        );
    }

    public function testSetPublic()
    {
        $def = new ChildDefinition('foo');

        $this->assertTrue($def->isPublic());
        $this->assertSame($def, $def->setPublic(false));
        $this->assertFalse($def->isPublic());
        $this->assertSame(array('public' => true), $def->getChanges());
    }

    public function testSetLazy()
    {
        $def = new ChildDefinition('foo');

        $this->assertFalse($def->isLazy());
        $this->assertSame($def, $def->setLazy(false));
        $this->assertFalse($def->isLazy());
        $this->assertSame(array('lazy' => true), $def->getChanges());
    }

    public function testSetAutowiredCalls()
    {
        $def = new ChildDefinition('foo');

        $this->assertFalse($def->isAutowired());
        $this->assertSame($def, $def->setAutowiredCalls(array('foo', 'bar')));
        $this->assertEquals(array('foo', 'bar'), $def->getAutowiredCalls());
        $this->assertSame(array('autowired_calls' => true), $def->getChanges());
    }

    public function testSetArgument()
    {
        $def = new ChildDefinition('foo');

        $this->assertSame(array(), $def->getArguments());
        $this->assertSame($def, $def->replaceArgument(0, 'foo'));
        $this->assertSame(array('index_0' => 'foo'), $def->getArguments());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testReplaceArgumentShouldRequireIntegerIndex()
    {
        $def = new ChildDefinition('foo');

        $def->replaceArgument('0', 'foo');
    }

    public function testReplaceArgument()
    {
        $def = new ChildDefinition('foo');

        $def->setArguments(array(0 => 'foo', 1 => 'bar'));
        $this->assertSame('foo', $def->getArgument(0));
        $this->assertSame('bar', $def->getArgument(1));

        $this->assertSame($def, $def->replaceArgument(1, 'baz'));
        $this->assertSame('foo', $def->getArgument(0));
        $this->assertSame('baz', $def->getArgument(1));

        $this->assertSame(array(0 => 'foo', 1 => 'bar', 'index_1' => 'baz'), $def->getArguments());
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testGetArgumentShouldCheckBounds()
    {
        $def = new ChildDefinition('foo');

        $def->setArguments(array(0 => 'foo'));
        $def->replaceArgument(0, 'foo');

        $def->getArgument(1);
    }

    public function testDefinitionDecoratorAliasExistsForBackwardsCompatibility()
    {
        $this->assertInstanceOf(ChildDefinition::class, new DefinitionDecorator('foo'));
    }
}
