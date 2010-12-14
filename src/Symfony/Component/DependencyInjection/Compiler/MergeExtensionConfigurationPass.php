<?php

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Merges extension configs into the container builder
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
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

        foreach ($container->getExtensionConfigs() as $name => $configs) {
            list($namespace, $tag) = explode(':', $name);

            $extension = $container->getExtension($namespace);

            $tmpContainer = new ContainerBuilder($container->getParameterBag());
            $tmpContainer->addObjectResource($extension);
            foreach ($configs as $config) {
                $extension->load($tag, $config, $tmpContainer);
            }

            $container->merge($tmpContainer);
        }

        $container->setExtensionConfigs(array());
        $container->addDefinitions($definitions);
        $container->addAliases($aliases);
        $container->getParameterBag()->add($parameters);
    }
}
