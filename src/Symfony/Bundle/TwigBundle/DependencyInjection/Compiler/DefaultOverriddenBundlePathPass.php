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
 * Registers the default overriding bundles paths.
 */
final class DefaultOverriddenBundlePathPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $twigLoaderFilesystemId = 'twig.loader.native_filesystem';

        if (false === $container->hasDefinition($twigLoaderFilesystemId)) {
            return;
        }

        $twigLoaderFilesystemDefinition = $container->getDefinition($twigLoaderFilesystemId);
        $twigDefaultPath = $container->getParameter('twig.default_path');

        foreach ($container->getParameter('kernel.bundles_metadata') as $name => $bundle) {
            $defaultOverrideBundlePath = $container->getParameterBag()->resolveValue($twigDefaultPath).'/bundles/'.$name;

            if (file_exists($dir = $container->getParameter('kernel.root_dir').'/Resources/'.$name.'/views')) {
                @trigger_error(sprintf('Templates directory "%s" is deprecated since Symfony 4.2, use "%s" instead.', $dir, $defaultOverrideBundlePath), E_USER_DEPRECATED);

                $twigLoaderFilesystemDefinition->addMethodCall(
                    'prependPath',
                    [$dir, $this->normalizeBundleName($name)]
                );
            }
            $container->addResource(new FileExistenceResource($dir));

            if (file_exists($defaultOverrideBundlePath)) {
                $twigLoaderFilesystemDefinition->addMethodCall(
                    'prependPath',
                    [$defaultOverrideBundlePath, $this->normalizeBundleName($name)]
                );
            }
            $container->addResource(new FileExistenceResource($defaultOverrideBundlePath));
        }
    }

    private function normalizeBundleName($name)
    {
        if ('Bundle' === substr($name, -6)) {
            $name = substr($name, 0, -6);
        }

        return $name;
    }
}
