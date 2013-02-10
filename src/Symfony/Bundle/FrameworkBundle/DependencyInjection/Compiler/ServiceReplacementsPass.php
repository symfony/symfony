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

use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Overwrites a service with an alias to a new service.
 *
 * @author Dariusz Górecki <darek.krk@gmail.com>
 * @author Christophe Coevoet <stof@notk.org>
 */
class ServiceReplacementsPass implements CompilerPassInterface
{
    /**
     * {@inheritDoc}
     */
    public function process(ContainerBuilder $container)
    {
        $replacements = array();

        foreach ($container->findTaggedServiceIds('framework.service_replacer') as $serviceId => $tag) {
            if (!isset($tag[0]['replaces'])) {
                continue;
            }

            $oldId = $tag[0]['replaces'];
            $newId = isset($tag[0]['renameTo'])
                ? $tag[0]['renameTo']
                : $oldId.'.orig';

            if ($container->hasAlias($oldId)) {
                // Service we're overwriting is an alias.
                // Register a private alias for this service to inject it as the parent
                $container->setAlias($newId, new Alias($oldId, false));
            } else {
                // Service we're overwriting is a definition.
                // Register it again as a private service to inject it as the parent.
                $definition = $container->getDefinition($oldId);
                $definition->setPublic(false);
                // Replacing definition should be properly tagged
                // so it's processed instead of old one.
                // Old service looses their tags.
                $definition->clearTags();
                $container->setDefinition($newId, $definition);
            }

            $container->setAlias($oldId, $serviceId);
        }
    }
}
