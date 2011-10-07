<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Adds all configured identity resolvers to the DomainObjectIdentityRetrievalStrategy
 *
 * @author Jordan Alliot <jordan.alliot@gmail.com>
 */
class AddIdentityResolversPass implements CompilerPassInterface
{
    /**
     * {@inheritDoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('security.acl.identity_resolver')) {
            return;
        }

        $identityResolvers = array();
        foreach ($container->findTaggedServiceIds('security.identity_resolver') as $id => $attributes) {
            $identityResolvers[] = new Reference($id);
        }

        $container->getDefinition('security.acl.identity_resolver')->replaceArgument(0, $identityResolvers);
    }
}
