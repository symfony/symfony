<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Loader;

use Symfony\Component\Routing\Annotation\Route;

class AnnotationClassLoaderTest extends AbstractAnnotationLoaderTest
{
    protected $loader;
    private $reader;

    protected function setUp()
    {
        parent::setUp();

        $this->reader = $this->getReader();
        $this->loader = $this->getClassLoader($this->reader);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testLoadMissingClass()
    {
        $this->loader->load('MissingClass');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testLoadAbstractClass()
    {
        $this->loader->load('Symfony\Component\Routing\Tests\Fixtures\AnnotatedClasses\AbstractClass');
    }

    /**
     * @dataProvider provideTestSupportsChecksResource
     */
    public function testSupportsChecksResource($resource, $expectedSupports)
    {
        $this->assertSame($expectedSupports, $this->loader->supports($resource), '->supports() returns true if the resource is loadable');
    }

    public function provideTestSupportsChecksResource()
    {
        return array(
            array('class', true),
            array('\fully\qualified\class\name', true),
            array('namespaced\class\without\leading\slash', true),
            array('ÿClassWithLegalSpecialCharacters', true),
            array('5', false),
            array('foo.foo', false),
            array(null, false),
        );
    }

    public function testSupportsChecksTypeIfSpecified()
    {
        $this->assertTrue($this->loader->supports('class', 'annotation'), '->supports() checks the resource type if specified');
        $this->assertFalse($this->loader->supports('class', 'foo'), '->supports() checks the resource type if specified');
    }

    public function getLoadTests()
    {
        return array(
            array(
                'Symfony\Component\Routing\Tests\Fixtures\AnnotatedClasses\BarClass',
                array('name' => 'route1', 'path' => '/path'),
                array('arg2' => 'defaultValue2', 'arg3' => 'defaultValue3'),
            ),
            array(
                'Symfony\Component\Routing\Tests\Fixtures\AnnotatedClasses\BarClass',
                array('defaults' => array('arg2' => 'foo'), 'requirements' => array('arg3' => '\w+')),
                array('arg2' => 'defaultValue2', 'arg3' => 'defaultValue3'),
            ),
            array(
                'Symfony\Component\Routing\Tests\Fixtures\AnnotatedClasses\BarClass',
                array('options' => array('foo' => 'bar')),
                array('arg2' => 'defaultValue2', 'arg3' => 'defaultValue3'),
            ),
            array(
                'Symfony\Component\Routing\Tests\Fixtures\AnnotatedClasses\BarClass',
                array('schemes' => array('https'), 'methods' => array('GET')),
                array('arg2' => 'defaultValue2', 'arg3' => 'defaultValue3'),
            ),
            array(
                'Symfony\Component\Routing\Tests\Fixtures\AnnotatedClasses\BarClass',
                array('condition' => 'context.getMethod() == "GET"'),
                array('arg2' => 'defaultValue2', 'arg3' => 'defaultValue3'),
            ),
        );
    }

    /**
     * @dataProvider getLoadTests
     */
    public function testLoad($className, $routeData = array(), $methodArgs = array())
    {
        $routeData = array_replace(array(
            'name' => 'route',
            'path' => '/',
            'requirements' => array(),
            'options' => array(),
            'defaults' => array(),
            'schemes' => array(),
            'methods' => array(),
            'condition' => '',
        ), $routeData);

        $this->reader
            ->expects($this->once())
            ->method('getMethodAnnotations')
            ->will($this->returnValue(array($this->getAnnotatedRoute($routeData))))
        ;

        $routeCollection = $this->loader->load($className);
        $route = $routeCollection->get($routeData['name']);

        $this->assertSame($routeData['path'], $route->getPath(), '->load preserves path annotation');
        $this->assertCount(
            count($routeData['requirements']),
            array_intersect_assoc($routeData['requirements'], $route->getRequirements()),
            '->load preserves requirements annotation'
        );
        $this->assertCount(
            count($routeData['options']),
            array_intersect_assoc($routeData['options'], $route->getOptions()),
            '->load preserves options annotation'
        );
        $defaults = array_replace($methodArgs, $routeData['defaults']);
        $this->assertCount(
            count($defaults),
            array_intersect_assoc($defaults, $route->getDefaults()),
            '->load preserves defaults annotation and merges them with default arguments in method signature'
        );
        $this->assertEquals($routeData['schemes'], $route->getSchemes(), '->load preserves schemes annotation');
        $this->assertEquals($routeData['methods'], $route->getMethods(), '->load preserves methods annotation');
        $this->assertSame($routeData['condition'], $route->getCondition(), '->load preserves condition annotation');
    }

    public function testClassRouteLoad()
    {
        $classRouteData = array(
            'path' => '/prefix',
            'schemes' => array('https'),
            'methods' => array('GET')
        );

        $methodRouteData = array(
            'name' => 'route1',
            'path' => '/path',
            'schemes' => array('http'),
            'methods' => array('POST', 'PUT')
        );

        $this->reader
            ->expects($this->once())
            ->method('getClassAnnotation')
            ->will($this->returnValue($this->getAnnotatedRoute($classRouteData)))
        ;
        $this->reader
            ->expects($this->once())
            ->method('getMethodAnnotations')
            ->will($this->returnValue(array($this->getAnnotatedRoute($methodRouteData))))
        ;

        $routeCollection = $this->loader->load('Symfony\Component\Routing\Tests\Fixtures\AnnotatedClasses\BarClass');
        $route = $routeCollection->get($methodRouteData['name']);

        $this->assertSame($classRouteData['path'].$methodRouteData['path'], $route->getPath(), '->load concatenates class and method route path');
        $this->assertEquals(array_merge($classRouteData['schemes'], $methodRouteData['schemes']), $route->getSchemes(), '->load merges class and method route schemes');
        $this->assertEquals(array_merge($classRouteData['methods'], $methodRouteData['methods']), $route->getMethods(), '->load merges class and method route methods');
    }

    private function getAnnotatedRoute($data)
    {
        return new Route($data);
    }
}
