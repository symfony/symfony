<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symfony\Bundle\FrameworkBundle\Secrets\SodiumVault;
use Symfony\Component\Config\Resource\FileExistenceResource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Path;

/**
 * Removes the env var loader of the secrets vault when the directory of the vault does not exist in the project.
 *
 * This spares building the vault when looking up env vars that are not defined.
 *
 * @internal
 */
class RemoveSecretsLoaderWithoutVaultPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('secrets.env_var_loader') || !$container->hasDefinition('secrets.vault')) {
            return;
        }

        $vault = $container->getDefinition('secrets.vault');

        if (SodiumVault::class !== $vault->getClass()) {
            return;
        }

        foreach ($container->getDefinitions() as $definition) {
            if ('secrets.vault' === ($definition->getDecoratedService()[0] ?? null)) {
                return;
            }
        }

        $bag = $container->getParameterBag();
        $dir = $bag->resolveValue($vault->getArguments()[0] ?? null);

        if (!\is_string($dir)) {
            return;
        }

        $dir = $bag->unescapeValue($container->resolveEnvPlaceholders($dir, "\0"));

        if (false !== $i = strpos($dir, "\0")) {
            // The path depends on env vars, like the runtime environment by default: check the directory that holds every vault
            $dir = \dirname(substr($dir, 0, $i).'.');
        }

        // A vault outside of the project can be a mount that exists only at runtime
        if (!$container->hasParameter('kernel.project_dir') || !Path::isBasePath($bag->unescapeValue($bag->resolveValue('%kernel.project_dir%')), $dir)) {
            return;
        }

        if (is_dir($dir)) {
            return;
        }

        $container->addResource(new FileExistenceResource($dir));
        $container->removeDefinition('secrets.env_var_loader');
    }
}
