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
                array(),
                array('name' => 'route1'),
                array('arg2' => 'defaultValue2', 'arg3' => 'defaultValue3')
            ),
            array(
                'Symfony\Component\Routing\Tests\Fixtures\AnnotatedClasses\BarClass',
                array(),
                array('name' => 'route1', 'defaults' => array('arg2' => 'foo')),
                array('arg2' => 'defaultValue2', 'arg3' => 'defaultValue3')
            ),
            array(
                'Symfony\Component\Routing\Tests\Fixtures\AnnotatedClasses\BarClass',
                array(),
                array('name' => 'route1', 'defaults' => array('arg2' => 'foobar')),
                array('arg2' => 'defaultValue2', 'arg3' =>'defaultValue3')
            ),
            array(
                'Symfony\Component\Routing\Tests\Fixtures\AnnotatedClasses\BarClass',
                array(),
                array('name' => 'route1', 'defaults' => array('arg2' => 'foo'), 'condition' => 'context.getMethod() == "GET"'),
                array('arg2' => 'defaultValue2', 'arg3' =>'defaultValue3')
            ),
            array(
                'Symfony\Component\Routing\Tests\Fixtures\AnnotatedClasses\BarClass',
                array(
                    'route1' => array('path' => '/routeprefix'),
                    'secondrouteprefix.route1' => array('path' => '/secondrouteprefix', 'name' => 'secondrouteprefix'),
                    'symfony_component_routing_tests_fixtures_annotatedclasses_barclass_routeaction' => array('path' => '/thirdrouteprefix'),
                    'symfony_component_routing_tests_fixtures_annotatedclasses_barclass_routeaction_1' => array('path' => '/anotherrouteprefix')
                ),
                array('name' => 'route1'),
                array('arg2' => 'defaultValue2', 'arg3' => 'defaultValue3')
            ),
            array(
                'Symfony\Component\Routing\Tests\Fixtures\AnnotatedClasses\BarClass',
                array(
                    'routeprefix.route1' => array('path' => '/routeprefix', 'name' => 'routeprefix'),
                    'route1' => array('path' => '/secondrouteprefix'),
                    'symfony_component_routing_tests_fixtures_annotatedclasses_barclass_routeaction' => array('path' => '/thirdrouteprefix'),
                    'anotherrouteprefix.route1' => array('path' => '/anotherrouteprefix', 'name' => 'anotherrouteprefix'),
                    'symfony_component_routing_tests_fixtures_annotatedclasses_barclass_routeaction_1' => array('path' => '/lastrouteprefix'),
                ),
                array('name' => 'route1'),
                array('arg2' => 'defaultValue2', 'arg3' => 'defaultValue3')
            ),
        );
    }

    /**
     * @dataProvider getLoadTests
     */
    public function testLoad($className, $classRouteDatas = array(), $routeDatas = array(), $methodArgs = array())
    {
        $routeDatas = array_replace(array(
            'name'         => 'route',
            'path'         => '/',
            'requirements' => array(),
            'options'      => array(),
            'defaults'     => array(),
            'schemes'      => array(),
            'methods'      => array(),
            'condition'    => null,
        ), $routeDatas);

        if ($classRouteDatas) {
            $classAnnotationsReturnValue = array();

            foreach ($classRouteDatas as $classRouteData) {
                $classAnnotationsReturnValue[] = $this->getAnnotatedRoute($classRouteData);
            }

            $this->reader
                ->expects($this->once())
                ->method('getClassAnnotations')
                ->will($this->returnValue($classAnnotationsReturnValue))
            ;
        }
        $this->reader
            ->expects($this->once())
            ->method('getMethodAnnotations')
            ->will($this->returnValue(array($this->getAnnotatedRoute($routeDatas))))
        ;
        $routeCollection = $this->loader->load($className);

        if (!$classRouteDatas) {
            $classRouteDatas = array($routeDatas['name'] => array('path' => ''));
        }

        foreach ($classRouteDatas as $routeName => $classRouteData) {
            $route = $routeCollection->get($routeName);
            $this->assertNotNull($route);

            $this->assertSame($classRouteData['path'].$routeDatas['path'], $route->getPath(), '->load preserves path annotation');
            $this->assertSame($routeDatas['requirements'],$route->getRequirements(), '->load preserves requirements annotation');
            $this->assertCount(0, array_intersect($route->getOptions(), $routeDatas['options']), '->load preserves options annotation');
            $this->assertSame(array_replace($methodArgs, $routeDatas['defaults']), $route->getDefaults(), '->load preserves defaults annotation');
            $this->assertEquals($routeDatas['condition'], $route->getCondition(), '->load preserves condition annotation');
        }
    }

    private function getAnnotatedRoute($datas)
    {
        return new Route($datas);
    }
}
