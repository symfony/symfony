<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabio B. Silva <fabio.bat.silva@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\DependencyInjection;

use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\AnnotationContainer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\AnnotationLoader;

require_once __DIR__ . '/Fixtures/includes/annotatedclasses.php';

class AnnotationContainerTest extends \PHPUnit_Framework_TestCase
{

    
     /**
     * @covers Symfony\Component\DependencyInjection\AnnotationContainer::compile
     */
    public function testCompile()
    {
        $pb = array(
            'annoted.foo' => new \FooAnnotatedClass(),
            'annoted.bar' => new \BarAnnotatedClass(),
        );
        
        $sc = new AnnotationContainer(new ParameterBag($pb));

        $sc->compile();
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag', $sc->getParameterBag(), '->compile() changes the parameter bag to a FrozenParameterBag instance');
        $this->assertEquals($pb, $sc->getParameterBag()->all(), '->compile() copies the current parameters to the new parameter bag');
    }
    
    
    /**
     * @covers Symfony\Component\DependencyInjection\AnnotationContainer::isFrozen
     */
    public function testIsFrozen()
    {
        $pb = array(
            'annoted.foo' => new \FooAnnotatedClass(),
            'annoted.bar' => new \BarAnnotatedClass(),
        );
        
        $sc = new AnnotationContainer(new ParameterBag($pb));
        $this->assertFalse($sc->isFrozen(), '->isFrozen() returns false if the parameters are not frozen');
        $sc->compile();
        $this->assertTrue($sc->isFrozen(), '->isFrozen() returns true if the parameters are frozen');
    }
    

    
    /**
     * @covers Symfony\Component\DependencyInjection\Container::get
     */
    public function testGet()
    {

        $sc = new AnnotationContainer();
        $sc->set('foo', $foo = new \SimpleFooClass());
        $sc->set('bar', $bar = new \SimpleBarClass());
        $sc->set('annoted.foo',$fooAnnoted = new \FooAnnotatedClass());
        $sc->set('annoted.bar',$barAnnoted = new \BarAnnotatedClass());
        $sc->set('annoted.foobar',$foobarAnnoted = new \FooBarAnnotatedClass());
        
        
        $this->assertEquals($bar,           $sc->get('bar'), '->get() returns the service for the given id');
        $this->assertEquals($barAnnoted,    $sc->get('annoted.bar'), '->get() returns the service for the given id');
        
        $this->assertEquals($foo,           $sc->get('foo'), '->get() returns the service for the given id');
        $this->assertEquals($fooAnnoted,    $sc->get('annoted.foo'), '->get() returns the service for the given id');
        
        $this->assertEquals($foobarAnnoted, $sc->get('annoted.foobar'), '->get() returns the service for the given id');

        $this->assertEquals($foobarAnnoted, $sc->get('annoted.foobar'), '->get() returns the service for the given id');
        $this->assertEquals($fooAnnoted,    $sc->get('annoted.foobar')->getFooAnnoted(), '->get() returns the service for the given id');
        $this->assertEquals($foo,           $sc->get('annoted.foobar')->getFooAnnoted()->getFoo(), '->get() returns the service for the given id');

    }
    
    
    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     */
    public function testNotExistentService()
    {
        $sc = new AnnotationContainer();
        $sc->set('foo',$foo = new \NotExistentServiceAnnotatedClass());
        $sc->get('foo');
    }

}