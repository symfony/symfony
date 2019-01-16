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
        return [
            ['class', true],
            ['\fully\qualified\class\name', true],
            ['namespaced\class\without\leading\slash', true],
            ['ÿClassWithLegalSpecialCharacters', true],
            ['5', false],
            ['foo.foo', false],
            [null, false],
        ];
    }

    public function testSupportsChecksTypeIfSpecified()
    {
        $this->assertTrue($this->loader->supports('class', 'annotation'), '->supports() checks the resource type if specified');
        $this->assertFalse($this->loader->supports('class', 'foo'), '->supports() checks the resource type if specified');
    }

    public function getLoadTests()
    {
        return [
            [
                'Symfony\Component\Routing\Tests\Fixtures\AnnotatedClasses\BarClass',
                ['name' => 'route1', 'path' => '/path'],
                ['arg2' => 'defaultValue2', 'arg3' => 'defaultValue3'],
            ],
            [
                'Symfony\Component\Routing\Tests\Fixtures\AnnotatedClasses\BarClass',
                ['defaults' => ['arg2' => 'foo'], 'requirements' => ['arg3' => '\w+']],
                ['arg2' => 'defaultValue2', 'arg3' => 'defaultValue3'],
            ],
            [
                'Symfony\Component\Routing\Tests\Fixtures\AnnotatedClasses\BarClass',
                ['options' => ['foo' => 'bar']],
                ['arg2' => 'defaultValue2', 'arg3' => 'defaultValue3'],
            ],
            [
                'Symfony\Component\Routing\Tests\Fixtures\AnnotatedClasses\BarClass',
                ['schemes' => ['https'], 'methods' => ['GET']],
                ['arg2' => 'defaultValue2', 'arg3' => 'defaultValue3'],
            ],
            [
                'Symfony\Component\Routing\Tests\Fixtures\AnnotatedClasses\BarClass',
                ['condition' => 'context.getMethod() == "GET"'],
                ['arg2' => 'defaultValue2', 'arg3' => 'defaultValue3'],
            ],
        ];
    }

    /**
     * @dataProvider getLoadTests
     */
    public function testLoad($className, $routeData = [], $methodArgs = [])
    {
        $routeData = array_replace([
            'name' => 'route',
            'path' => '/',
            'requirements' => [],
            'options' => [],
            'defaults' => [],
            'schemes' => [],
            'methods' => [],
            'condition' => '',
        ], $routeData);

        $this->reader
            ->expects($this->once())
            ->method('getMethodAnnotations')
            ->will($this->returnValue([$this->getAnnotatedRoute($routeData)]))
        ;

        $routeCollection = $this->loader->load($className);
        $route = $routeCollection->get($routeData['name']);

        $this->assertSame($routeData['path'], $route->getPath(), '->load preserves path annotation');
        $this->assertCount(
            \count($routeData['requirements']),
            array_intersect_assoc($routeData['requirements'], $route->getRequirements()),
            '->load preserves requirements annotation'
        );
        $this->assertCount(
            \count($routeData['options']),
            array_intersect_assoc($routeData['options'], $route->getOptions()),
            '->load preserves options annotation'
        );
        $this->assertCount(
            \count($routeData['defaults']),
            $route->getDefaults(),
            '->load preserves defaults annotation'
        );
        $this->assertEquals($routeData['schemes'], $route->getSchemes(), '->load preserves schemes annotation');
        $this->assertEquals($routeData['methods'], $route->getMethods(), '->load preserves methods annotation');
        $this->assertSame($routeData['condition'], $route->getCondition(), '->load preserves condition annotation');
    }

    public function testClassRouteLoad()
    {
        $classRouteData = [
            'name' => 'prefix_',
            'path' => '/prefix',
            'schemes' => ['https'],
            'methods' => ['GET'],
        ];

        $methodRouteData = [
            'name' => 'route1',
            'path' => '/path',
            'schemes' => ['http'],
            'methods' => ['POST', 'PUT'],
        ];

        $this->reader
            ->expects($this->once())
            ->method('getClassAnnotation')
            ->will($this->returnValue($this->getAnnotatedRoute($classRouteData)))
        ;
        $this->reader
            ->expects($this->once())
            ->method('getMethodAnnotations')
            ->will($this->returnValue([$this->getAnnotatedRoute($methodRouteData)]))
        ;

        $routeCollection = $this->loader->load('Symfony\Component\Routing\Tests\Fixtures\AnnotatedClasses\BarClass');
        $route = $routeCollection->get($classRouteData['name'].$methodRouteData['name']);

        $this->assertSame($classRouteData['path'].$methodRouteData['path'], $route->getPath(), '->load concatenates class and method route path');
        $this->assertEquals(array_merge($classRouteData['schemes'], $methodRouteData['schemes']), $route->getSchemes(), '->load merges class and method route schemes');
        $this->assertEquals(array_merge($classRouteData['methods'], $methodRouteData['methods']), $route->getMethods(), '->load merges class and method route methods');
    }

    public function testInvokableClassRouteLoadWithMethodAnnotation()
    {
        $classRouteData = [
            'name' => 'route1',
            'path' => '/',
            'schemes' => ['https'],
            'methods' => ['GET'],
        ];

        $this->reader
            ->expects($this->exactly(1))
            ->method('getClassAnnotations')
            ->will($this->returnValue([$this->getAnnotatedRoute($classRouteData)]))
        ;
        $this->reader
            ->expects($this->once())
            ->method('getMethodAnnotations')
            ->will($this->returnValue([]))
        ;

        $routeCollection = $this->loader->load('Symfony\Component\Routing\Tests\Fixtures\AnnotatedClasses\BazClass');
        $route = $routeCollection->get($classRouteData['name']);

        $this->assertSame($classRouteData['path'], $route->getPath(), '->load preserves class route path');
        $this->assertEquals($classRouteData['schemes'], $route->getSchemes(), '->load preserves class route schemes');
        $this->assertEquals($classRouteData['methods'], $route->getMethods(), '->load preserves class route methods');
    }

    public function testInvokableClassRouteLoadWithClassAnnotation()
    {
        $classRouteData = [
            'name' => 'route1',
            'path' => '/',
            'schemes' => ['https'],
            'methods' => ['GET'],
        ];

        $this->reader
            ->expects($this->exactly(1))
            ->method('getClassAnnotation')
            ->will($this->returnValue($this->getAnnotatedRoute($classRouteData)))
        ;

        $this->reader
            ->expects($this->exactly(1))
            ->method('getClassAnnotations')
            ->will($this->returnValue([$this->getAnnotatedRoute($classRouteData)]))
        ;

        $this->reader
            ->expects($this->once())
            ->method('getMethodAnnotations')
            ->will($this->returnValue([]))
        ;

        $routeCollection = $this->loader->load('Symfony\Component\Routing\Tests\Fixtures\AnnotatedClasses\BazClass');
        $route = $routeCollection->get($classRouteData['name']);

        $this->assertSame($classRouteData['path'], $route->getPath(), '->load preserves class route path');
        $this->assertEquals($classRouteData['schemes'], $route->getSchemes(), '->load preserves class route schemes');
        $this->assertEquals($classRouteData['methods'], $route->getMethods(), '->load preserves class route methods');
    }

    public function testInvokableClassMultipleRouteLoad()
    {
        $classRouteData1 = [
            'name' => 'route1',
            'path' => '/1',
            'schemes' => ['https'],
            'methods' => ['GET'],
        ];

        $classRouteData2 = [
            'name' => 'route2',
            'path' => '/2',
            'schemes' => ['https'],
            'methods' => ['GET'],
        ];

        $this->reader
            ->expects($this->exactly(1))
            ->method('getClassAnnotations')
            ->will($this->returnValue([$this->getAnnotatedRoute($classRouteData1), $this->getAnnotatedRoute($classRouteData2)]))
        ;
        $this->reader
            ->expects($this->once())
            ->method('getMethodAnnotations')
            ->will($this->returnValue([]))
        ;

        $routeCollection = $this->loader->load('Symfony\Component\Routing\Tests\Fixtures\AnnotatedClasses\BazClass');
        $route = $routeCollection->get($classRouteData1['name']);

        $this->assertSame($classRouteData1['path'], $route->getPath(), '->load preserves class route path');
        $this->assertEquals($classRouteData1['schemes'], $route->getSchemes(), '->load preserves class route schemes');
        $this->assertEquals($classRouteData1['methods'], $route->getMethods(), '->load preserves class route methods');

        $route = $routeCollection->get($classRouteData2['name']);

        $this->assertSame($classRouteData2['path'], $route->getPath(), '->load preserves class route path');
        $this->assertEquals($classRouteData2['schemes'], $route->getSchemes(), '->load preserves class route schemes');
        $this->assertEquals($classRouteData2['methods'], $route->getMethods(), '->load preserves class route methods');
    }

    public function testInvokableClassWithMethodRouteLoad()
    {
        $classRouteData = [
            'name' => 'route1',
            'path' => '/prefix',
            'schemes' => ['https'],
            'methods' => ['GET'],
        ];

        $methodRouteData = [
            'name' => 'route2',
            'path' => '/path',
            'schemes' => ['http'],
            'methods' => ['POST', 'PUT'],
        ];

        $this->reader
            ->expects($this->once())
            ->method('getClassAnnotation')
            ->will($this->returnValue($this->getAnnotatedRoute($classRouteData)))
        ;
        $this->reader
            ->expects($this->once())
            ->method('getMethodAnnotations')
            ->will($this->returnValue([$this->getAnnotatedRoute($methodRouteData)]))
        ;

        $routeCollection = $this->loader->load('Symfony\Component\Routing\Tests\Fixtures\AnnotatedClasses\BazClass');
        $route = $routeCollection->get($classRouteData['name']);

        $this->assertNull($route, '->load ignores class route');

        $route = $routeCollection->get($classRouteData['name'].$methodRouteData['name']);

        $this->assertSame($classRouteData['path'].$methodRouteData['path'], $route->getPath(), '->load concatenates class and method route path');
        $this->assertEquals(array_merge($classRouteData['schemes'], $methodRouteData['schemes']), $route->getSchemes(), '->load merges class and method route schemes');
        $this->assertEquals(array_merge($classRouteData['methods'], $methodRouteData['methods']), $route->getMethods(), '->load merges class and method route methods');
    }

    private function getAnnotatedRoute($data)
    {
        return new Route($data);
    }
}
