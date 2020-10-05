<?php

namespace Symfony\Component\Routing\Tests\Loader;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Symfony\Component\Routing\Loader\AnnotationClassLoader;
use Symfony\Component\Routing\Route;

class AnnotationClassLoaderWithAnnotationsTest extends AnnotationClassLoaderTest
{
    protected function setUp(): void
    {
        $reader = new AnnotationReader();
        $this->loader = new class($reader) extends AnnotationClassLoader {
            protected function configureRoute(Route $route, \ReflectionClass $class, \ReflectionMethod $method, object $annot): void
            {
            }
        };
        AnnotationRegistry::registerLoader('class_exists');
    }

    public function testDefaultRouteName()
    {
        $routeCollection = $this->loader->load($this->getNamespace().'\EncodingClass');
        $defaultName = array_keys($routeCollection->all())[0];

        $this->assertSame('symfony_component_routing_tests_fixtures_annotationfixtures_encodingclass_routeàction', $defaultName);
    }

    protected function getNamespace(): string
    {
        return 'Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures';
    }
}
