<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;

/**
 * JsonLoginLdapFactory creates services for json login ldap authentication.
 *
 * @internal
 */
class JsonLoginLdapFactory extends JsonLoginFactory
{
    use LdapFactoryTrait;

    public function addConfiguration(NodeDefinition $node)
    {
        parent::addConfiguration($node);

        $node
            ->children()
                ->scalarNode('service')->defaultValue('ldap')->end()
                ->scalarNode('dn_string')->defaultValue('{username}')->end()
                ->scalarNode('query_string')->end()
                ->scalarNode('search_dn')->defaultValue('')->end()
                ->scalarNode('search_password')->defaultValue('')->end()
            ->end()
        ;
    }
}
