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

use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Component\Routing\Tests\Fixtures\TraceableAttributeClassLoader;

/**
 * @group legacy
 */
class AttributeClassLoaderWithAnnotationsTest extends AttributeClassLoaderTestCase
{
    protected function setUp(?string $env = null): void
    {
        $reader = new AnnotationReader();
        $this->loader = new TraceableAttributeClassLoader($reader, $env);
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
