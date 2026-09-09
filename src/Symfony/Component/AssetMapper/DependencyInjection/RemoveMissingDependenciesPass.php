<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Drops the services that the container cannot wire, because what they depend on is missing.
 *
 * @internal
 */
class RemoveMissingDependenciesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('asset_mapper')) {
            return;
        }

        if (!$container->has('assets._default_package')) {
            $container->removeDefinition('asset_mapper.asset_package');
        }

        if (!$container->has('http_client')) {
            $container->register('asset_mapper.http_client', HttpClientInterface::class)
                ->addTag('container.error')
                ->addError('You cannot use the AssetMapper integration since the HttpClient component is not enabled. Try enabling the "framework.http_client" config option.');
        }

        if (!$container->has('cache.system')) {
            $container->removeDefinition('cache.asset_mapper');
        }
    }
}
