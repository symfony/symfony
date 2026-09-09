<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\DependencyInjection\RemoveMissingDependenciesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class RemoveMissingDependenciesPassTest extends TestCase
{
    public function testEverythingIsKeptWhenAllDependenciesAreThere()
    {
        $container = $this->createContainer();
        $container->register('assets._default_package');
        $container->register('http_client');
        $container->register('cache.system');

        new RemoveMissingDependenciesPass()->process($container);

        $this->assertTrue($container->hasDefinition('asset_mapper.asset_package'));
        $this->assertTrue($container->hasDefinition('cache.asset_mapper'));
        $this->assertSame('http_client', (string) $container->getAlias('asset_mapper.http_client'));
    }

    public function testTheAssetPackageIsDroppedWithoutTheDefaultPackage()
    {
        $container = $this->createContainer();

        new RemoveMissingDependenciesPass()->process($container);

        $this->assertFalse($container->hasDefinition('asset_mapper.asset_package'));
    }

    public function testTheCachePoolIsDroppedWithoutTheSystemPool()
    {
        $container = $this->createContainer();

        new RemoveMissingDependenciesPass()->process($container);

        $this->assertFalse($container->hasDefinition('cache.asset_mapper'));
    }

    public function testTheHttpClientIsTurnedIntoAnErrorWhenMissing()
    {
        $container = $this->createContainer();

        new RemoveMissingDependenciesPass()->process($container);

        $this->assertFalse($container->hasAlias('asset_mapper.http_client'));
        $this->assertSame(['You cannot use the AssetMapper integration since the HttpClient component is not enabled. Try enabling the "framework.http_client" config option.'], $container->getDefinition('asset_mapper.http_client')->getErrors());
    }

    public function testNothingHappensWhenTheAssetMapperIsDisabled()
    {
        $container = new ContainerBuilder();

        new RemoveMissingDependenciesPass()->process($container);

        $this->assertFalse($container->has('asset_mapper.http_client'));
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('asset_mapper');
        $container->register('asset_mapper.asset_package');
        $container->register('cache.asset_mapper');
        $container->setAlias('asset_mapper.http_client', 'http_client');

        return $container;
    }
}
