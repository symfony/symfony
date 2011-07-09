<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Merges extension configs into the container builder
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class MergeExtensionConfigurationPass implements CompilerPassInterface
{
    /**
     * {@inheritDoc}
     */
    public function process(ContainerBuilder $container)
    {
        $parameters = $container->getParameterBag()->all();
        $definitions = $container->getDefinitions();
        $aliases = $container->getAliases();

        foreach ($container->getExtensions() as $name => $extension) {
            if (!$config = $container->getExtensionConfig($name)) {
                // this extension was not called
                continue;
            }
            $config = $container->getParameterBag()->resolveValue($config);

            $tmpContainer = new ContainerBuilder($container->getParameterBag());
            $tmpContainer->addObjectResource($extension);

            $extension->load($config, $tmpContainer);

            $container->merge($tmpContainer);
        }

        $container->addDefinitions($definitions);
        $container->addAliases($aliases);
        $container->getParameterBag()->add($parameters);
    }
}
