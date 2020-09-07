<?php

namespace Symfony\Component\Routing\Tests\Loader;

use Symfony\Component\Routing\Loader\AnnotationClassLoader;
use Symfony\Component\Routing\Route;

/**
 * @requires PHP 8
 */
class AnnotationClassLoaderWithAttributesTest extends AnnotationClassLoaderTest
{
    protected function setUp(): void
    {
        $this->loader = new class() extends AnnotationClassLoader {
            protected function configureRoute(Route $route, \ReflectionClass $class, \ReflectionMethod $method, object $annot): void
            {
            }
        };
    }

    public function testDefaultRouteName()
    {
        $routeCollection = $this->loader->load($this->getNamespace().'\EncodingClass');
        $defaultName = array_keys($routeCollection->all())[0];

        $this->assertSame('symfony_component_routing_tests_fixtures_attributefixtures_encodingclass_routeàction', $defaultName);
    }

    protected function getNamespace(): string
    {
        return 'Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures';
    }
}
