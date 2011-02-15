<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;

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

        foreach ($container->getExtensions() as $name => $extension) {
            $tmpContainer = new ContainerBuilder($container->getParameterBag());
            $tmpContainer->addObjectResource($extension);

            $extension->load($container->getExtensionConfig($name), $tmpContainer);

            $container->merge($tmpContainer);
        }

        $container->addDefinitions($definitions);
        $container->addAliases($aliases);
        $container->getParameterBag()->add($parameters);
    }
}
