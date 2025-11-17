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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Routing\Loader\AttributeServicesLoader;
use Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\ActionPathController;
use Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\MethodActionControllers;
use Symfony\Component\Routing\Tests\Fixtures\TraceableAttributeClassLoader;

class AttributeServicesLoaderTest extends TestCase
{
    public function testSupports()
    {
        $loader = new AttributeServicesLoader();

        $this->assertFalse($loader->supports('attributes', null));
        $this->assertFalse($loader->supports('attributes', 'attribute'));
        $this->assertFalse($loader->supports('other', 'routing.controllers'));
        $this->assertTrue($loader->supports('routing.controllers'));
    }

    public function testDelegatesToAttributeLoaderAndMergesCollections()
    {
        $attributeLoader = new TraceableAttributeClassLoader();

        $servicesLoader = new AttributeServicesLoader([
            ActionPathController::class,
            MethodActionControllers::class,
        ]);

        $resolver = new LoaderResolver([
            $attributeLoader,
            $servicesLoader,
        ]);

        $attributeLoader->setResolver($resolver);
        $servicesLoader->setResolver($resolver);

        $collection = $servicesLoader->load('routing.controllers');

        $this->assertArrayHasKey('action', $collection->all());
        $this->assertArrayHasKey('put', $collection->all());
        $this->assertArrayHasKey('post', $collection->all());

        $this->assertSame(['/path'], [$collection->get('action')->getPath()]);
        $this->assertSame('/the/path', $collection->get('put')->getPath());
        $this->assertSame('/the/path', $collection->get('post')->getPath());

        $this->assertSame([
            ActionPathController::class,
            MethodActionControllers::class,
        ], $attributeLoader->foundClasses);
    }
}
