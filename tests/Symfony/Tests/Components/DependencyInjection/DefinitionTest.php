<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\DependencyInjection;

require_once __DIR__.'/../../bootstrap.php';

use Symfony\Components\DependencyInjection\Definition;

class DefinitionTest extends \PHPUnit_Framework_TestCase
{
  public function testConstructor()
  {
    $def = new Definition('stdClass');
    $this->assertEquals($def->getClass(), 'stdClass', '__construct() takes the class name as its first argument');

    $def = new Definition('stdClass', array('foo'));
    $this->assertEquals($def->getArguments(), array('foo'), '__construct() takes an optional array of arguments as its second argument');
  }

  public function testSetGetConstructor()
  {
    $def = new Definition('stdClass');
    $this->assertEquals(spl_object_hash($def->setConstructor('foo')), spl_object_hash($def), '->setConstructor() implements a fluent interface');
    $this->assertEquals($def->getConstructor(), 'foo', '->getConstructor() returns the constructor name');
  }

  public function testSetGetClass()
  {
    $def = new Definition('stdClass');
    $this->assertEquals(spl_object_hash($def->setClass('foo')), spl_object_hash($def), '->setClass() implements a fluent interface');
    $this->assertEquals($def->getClass(), 'foo', '->getClass() returns the class name');
  }

  public function testArguments()
  {
    $def = new Definition('stdClass');
    $this->assertEquals(spl_object_hash($def->setArguments(array('foo'))), spl_object_hash($def), '->setArguments() implements a fluent interface');
    $this->assertEquals($def->getArguments(), array('foo'), '->getArguments() returns the arguments');
    $this->assertEquals(spl_object_hash($def->addArgument('bar')), spl_object_hash($def), '->addArgument() implements a fluent interface');
    $this->assertEquals($def->getArguments(), array('foo', 'bar'), '->addArgument() adds an argument');
  }

  public function testMethodCalls()
  {
    $def = new Definition('stdClass');
    $this->assertEquals(spl_object_hash($def->setMethodCalls(array(array('foo', array('foo'))))), spl_object_hash($def), '->setMethodCalls() implements a fluent interface');
    $this->assertEquals($def->getMethodCalls(), array(array('foo', array('foo'))), '->getMethodCalls() returns the methods to call');
    $this->assertEquals(spl_object_hash($def->addMethodCall('bar', array('bar'))), spl_object_hash($def), '->addMethodCall() implements a fluent interface');
    $this->assertEquals($def->getMethodCalls(), array(array('foo', array('foo')), array('bar', array('bar'))), '->addMethodCall() adds a method to call');
  }

  public function testSetGetFile()
  {
    $def = new Definition('stdClass');
    $this->assertEquals(spl_object_hash($def->setFile('foo')), spl_object_hash($def), '->setFile() implements a fluent interface');
    $this->assertEquals($def->getFile(), 'foo', '->getFile() returns the file to include');
  }

  public function testSetIsShared()
  {
    $def = new Definition('stdClass');
    $this->assertEquals($def->isShared(), true, '->isShared() returns true by default');
    $this->assertEquals(spl_object_hash($def->setShared(false)), spl_object_hash($def), '->setShared() implements a fluent interface');
    $this->assertEquals($def->isShared(), false, '->isShared() returns false if the instance must not be shared');
  }

  public function testSetGetConfigurator()
  {
    $def = new Definition('stdClass');
    $this->assertEquals(spl_object_hash($def->setConfigurator('foo')), spl_object_hash($def), '->setConfigurator() implements a fluent interface');
    $this->assertEquals($def->getConfigurator(), 'foo', '->getConfigurator() returns the configurator');
  }

  public function testAnnotations()
  {
    $def = new Definition('stdClass');
    $this->assertEquals(spl_object_hash($def->addAnnotation('foo')), spl_object_hash($def), '->addAnnotation() implements a fluent interface');
    $this->assertEquals($def->getAnnotation('foo'), array(array()), '->getAnnotation() returns attributes for an annotation name');
    $def->addAnnotation('foo', array('foo' => 'bar'));
    $this->assertEquals($def->getAnnotation('foo'), array(array(), array('foo' => 'bar')), '->addAnnotation() can adds the same annotation several times');
    $def->addAnnotation('bar', array('bar' => 'bar'));
    $this->assertEquals($def->getAnnotations(), array(
      'foo' => array(array(), array('foo' => 'bar')),
      'bar' => array(array('bar' => 'bar')),
    ), '->getAnnotations() returns all annotations');
  }
}
