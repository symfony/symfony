<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Wires what only another bundle can provide, and drops the services that would be left dangling.
 *
 * @internal
 */
class RemoveMissingDependenciesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->hasParameter('.validator.translation_domain')) {
            // FrameworkBundle defaults this parameter for the services that reference it whether or not
            // validation is enabled, and which extension is loaded first is not something to depend on
            $container->setParameter('validator.translation_domain', $container->getParameter('.validator.translation_domain'));
            $container->getParameterBag()->remove('.validator.translation_domain');
        }

        if (!$container->hasDefinition('validator.mapping.cache.adapter') && $container->hasDefinition('validator.builder')) {
            $container->getDefinition('validator.builder')->removeMethodCall('setMappingCache');
        }
    }
}
