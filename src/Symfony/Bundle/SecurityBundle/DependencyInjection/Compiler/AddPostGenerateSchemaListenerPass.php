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

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Adds a post generate schema listener to merge the ACL schema.
 *
 * @author Victor Berchet <victor.berchet@sensiolabs.com>
 */
class AddPostGenerateSchemaListenerPass implements CompilerPassInterface
{
    /**
     * {@inheritDoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasParameter('security.acl.dbal.connection_name')) {
            return;
        }

        $connection = $container->getParameter('security.acl.dbal.connection_name');

        $connection = is_null($connection)
            ? $container->getParameter('doctrine.default_connection')
            : $connection;

        $container->getDefinition(sprintf('doctrine.dbal.%s_connection.event_manager', $connection))->addMethodCall(
            'addEventListener',
            array('postGenerateSchema', 'security.acl.dbal.schema_listener')
        );
    }
}
