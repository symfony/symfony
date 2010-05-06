<?php

/*
 * This file is part of the Symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\DependencyInjection;

use Symfony\Components\DependencyInjection\Definition;

class DefinitionTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $def = new Definition('stdClass');
        $this->assertEquals('stdClass', $def->getClass(), '__construct() takes the class name as its first argument');

        $def = new Definition('stdClass', array('foo'));
        $this->assertEquals(array('foo'), $def->getArguments(), '__construct() takes an optional array of arguments as its second argument');
    }

    public function testSetGetConstructor()
    {
        $def = new Definition('stdClass');
        $this->assertEquals(spl_object_hash($def), spl_object_hash($def->setConstructor('foo')), '->setConstructor() implements a fluent interface');
        $this->assertEquals('foo', $def->getConstructor(), '->getConstructor() returns the constructor name');
    }

    public function testSetGetClass()
    {
        $def = new Definition('stdClass');
        $this->assertEquals(spl_object_hash($def), spl_object_hash($def->setClass('foo')), '->setClass() implements a fluent interface');
        $this->assertEquals('foo', $def->getClass(), '->getClass() returns the class name');
    }

    public function testArguments()
    {
        $def = new Definition('stdClass');
        $this->assertEquals(spl_object_hash($def), spl_object_hash($def->setArguments(array('foo'))), '->setArguments() implements a fluent interface');
        $this->assertEquals(array('foo'), $def->getArguments(), '->getArguments() returns the arguments');
        $this->assertEquals(spl_object_hash($def), spl_object_hash($def->addArgument('bar')), '->addArgument() implements a fluent interface');
        $this->assertEquals(array('foo', 'bar'), $def->getArguments(), '->addArgument() adds an argument');
    }

    public function testMethodCalls()
    {
        $def = new Definition('stdClass');
        $this->assertEquals(spl_object_hash($def), spl_object_hash($def->setMethodCalls(array(array('foo', array('foo'))))), '->setMethodCalls() implements a fluent interface');
        $this->assertEquals(array(array('foo', array('foo'))), $def->getMethodCalls(), '->getMethodCalls() returns the methods to call');
        $this->assertEquals(spl_object_hash($def), spl_object_hash($def->addMethodCall('bar', array('bar'))), '->addMethodCall() implements a fluent interface');
        $this->assertEquals(array(array('foo', array('foo')), array('bar', array('bar'))), $def->getMethodCalls(), '->addMethodCall() adds a method to call');
    }

    public function testSetGetFile()
    {
        $def = new Definition('stdClass');
        $this->assertEquals(spl_object_hash($def), spl_object_hash($def->setFile('foo')), '->setFile() implements a fluent interface');
        $this->assertEquals('foo', $def->getFile(), '->getFile() returns the file to include');
    }

    public function testSetIsShared()
    {
        $def = new Definition('stdClass');
        $this->assertTrue($def->isShared(), '->isShared() returns true by default');
        $this->assertEquals(spl_object_hash($def), spl_object_hash($def->setShared(false)), '->setShared() implements a fluent interface');
        $this->assertFalse($def->isShared(), '->isShared() returns false if the instance must not be shared');
    }

    public function testSetGetConfigurator()
    {
        $def = new Definition('stdClass');
        $this->assertEquals(spl_object_hash($def), spl_object_hash($def->setConfigurator('foo')), '->setConfigurator() implements a fluent interface');
        $this->assertEquals('foo', $def->getConfigurator(), '->getConfigurator() returns the configurator');
    }

    public function testAnnotations()
    {
        $def = new Definition('stdClass');
        $this->assertEquals(spl_object_hash($def), spl_object_hash($def->addAnnotation('foo')), '->addAnnotation() implements a fluent interface');
        $this->assertEquals(array(array()), $def->getAnnotation('foo'), '->getAnnotation() returns attributes for an annotation name');
        $def->addAnnotation('foo', array('foo' => 'bar'));
        $this->assertEquals(array(array(), array('foo' => 'bar')), $def->getAnnotation('foo'), '->addAnnotation() can adds the same annotation several times');
        $def->addAnnotation('bar', array('bar' => 'bar'));
        $this->assertEquals($def->getAnnotations(), array(
            'foo' => array(array(), array('foo' => 'bar')),
            'bar' => array(array('bar' => 'bar')),
        ), '->getAnnotations() returns all annotations');
    }
}
