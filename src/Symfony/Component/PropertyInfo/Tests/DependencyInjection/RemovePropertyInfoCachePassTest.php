<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\PropertyInfo\DependencyInjection\RemovePropertyInfoCachePass;
use Symfony\Component\PropertyInfo\PropertyInfoCacheExtractor;

class RemovePropertyInfoCachePassTest extends TestCase
{
    public function testTheCachingExtractorIsKeptWhenTheSystemPoolIsAvailable()
    {
        $container = $this->createContainer();
        $container->register('cache.system');

        new RemovePropertyInfoCachePass()->process($container);

        $this->assertTrue($container->hasDefinition('cache.property_info'));
        $this->assertTrue($container->hasDefinition('property_info.cache'));
    }

    public function testTheCachingExtractorIsRemovedWhenTheSystemPoolIsMissing()
    {
        $container = $this->createContainer();

        new RemovePropertyInfoCachePass()->process($container);

        $this->assertFalse($container->hasDefinition('cache.property_info'));
        $this->assertFalse($container->hasDefinition('property_info.cache'));
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition('cache.property_info', new ChildDefinition('cache.system'));
        $container->register('property_info.cache', PropertyInfoCacheExtractor::class);

        return $container;
    }
}
