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

use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface as FrameworkBundleEngineInterface;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Templating\EngineInterface as ComponentEngineInterface;

/**
 * @deprecated since version 4.3, to be removed in 5.0; use Twig instead.
 */
class TemplatingPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if ($container->hasDefinition('templating')) {
            return;
        }

        if ($container->hasAlias('templating')) {
            $container->setAlias(ComponentEngineInterface::class, new Alias('templating', false));
            $container->setAlias(FrameworkBundleEngineInterface::class, new Alias('templating', false));
        }

        if ($container->hasDefinition('templating.engine.php')) {
            $refs = [];
            $helpers = [];

            foreach ($container->findTaggedServiceIds('templating.helper', true) as $id => $attributes) {
                if (!$container->getDefinition($id)->isDeprecated()) {
                    @trigger_error('The "templating.helper" tag is deprecated since version 4.3 and will be removed in 5.0; use Twig instead.', E_USER_DEPRECATED);
                }

                if (isset($attributes[0]['alias'])) {
                    $helpers[$attributes[0]['alias']] = $id;
                    $refs[$id] = new Reference($id);
                }
            }

            if (\count($helpers) > 0) {
                $definition = $container->getDefinition('templating.engine.php');
                $definition->addMethodCall('setHelpers', [$helpers]);

                if ($container->hasDefinition('templating.engine.php.helpers_locator')) {
                    $container->getDefinition('templating.engine.php.helpers_locator')->replaceArgument(0, $refs);
                }
            }
        }
    }
}
