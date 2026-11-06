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

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\Loader\AttributeRouteControllerLoader as BaseAttributeRouteControllerLoader;

/**
 * Registers the "routing.loader.attribute" service with the deprecated
 * FrameworkBundle loader class so that type-hints against it keep working.
 *
 * The deprecated class extends the Routing component class, so type-hints
 * against either class work. The service switches back to the component
 * class in 9.0, when the deprecated class is removed.
 *
 * @internal
 */
class AttributeRouteControllerLoaderPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('routing.loader.attribute')) {
            return;
        }

        $definition = $container->getDefinition('routing.loader.attribute');

        if (BaseAttributeRouteControllerLoader::class !== $definition->getClass()) {
            return;
        }

        // The class is referenced by its FQCN string because it is deprecated:
        // using ::class on it is flagged by PHPStan's deprecation rules.
        $definition->setClass('Symfony\\Bundle\\FrameworkBundle\\Routing\\AttributeRouteControllerLoader');
    }
}
