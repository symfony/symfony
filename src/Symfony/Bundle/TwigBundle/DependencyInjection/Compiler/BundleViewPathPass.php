<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\DependencyInjection\Compiler;

use Symfony\Component\Config\Resource\FileExistenceResource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Registers the bundles view paths.
 */
final class BundleViewPathPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $twigFilesystemLoaderId = 'twig.loader.filesystem';
        if ($container->hasDefinition($twigFilesystemLoaderId)) {
            $twigFilesystemLoaderDefinition = $container->getDefinition($twigFilesystemLoaderId);
        } elseif ($container->hasAlias($twigFilesystemLoaderId)) {
            $aliasedService = (string) $container->getAlias($twigFilesystemLoaderId);
            if (false === $container->hasDefinition($aliasedService)) {
                return;
            }
            $twigFilesystemLoaderDefinition = $container->getDefinition($aliasedService);
        } else {
            return;
        }

        foreach ($container->getParameter('kernel.bundles_metadata') as $name => $bundle) {
            if (file_exists($dir = $bundle['path'].'/Resources/views')) {
                $namespace = $this->normalizeBundleName($name);
                $twigFilesystemLoaderDefinition->addMethodCall('addPath', [$dir, $namespace]);
                $twigFilesystemLoaderDefinition->addMethodCall('addPath', [$dir, '!'.$namespace]);
            }
            $container->addResource(new FileExistenceResource($dir));
        }
    }

    private function normalizeBundleName(string $name): string
    {
        if ('Bundle' === substr($name, -6)) {
            $name = substr($name, 0, -6);
        }

        return $name;
    }
}
