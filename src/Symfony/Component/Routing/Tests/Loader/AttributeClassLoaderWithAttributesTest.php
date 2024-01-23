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

use Symfony\Component\Routing\Tests\Fixtures\TraceableAttributeClassLoader;

class AttributeClassLoaderWithAttributesTest extends AttributeClassLoaderTestCase
{
    protected function setUp(?string $env = null): void
    {
        $this->loader = new TraceableAttributeClassLoader($env);
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
