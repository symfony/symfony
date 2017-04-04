<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\FirewallPostAuthenticationBundle\DependencyInjection\Factory;

use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\SecurityFactoryInterface;
use Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\FirewallPostAuthenticationBundle\Security\CustomAccessListener;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author John Kleijn <john@kleijnweb.nl>
 */
class CustomAccessFactory implements SecurityFactoryInterface
{
    const ID = 'security.authentication.listener.some_dummy_post_authentication_plugin';

    public function getPosition()
    {
        return 'post_authentication';
    }

    public function getKey()
    {
        return 'custom_access';
    }

    public function addConfiguration(NodeDefinition $node)
    {
        //NOOP
    }

    public function create(ContainerBuilder $container, $id, $config, $userProvider, $defaultEntryPoint)
    {
        $definition = new Definition(CustomAccessListener::class);
        $definition->addArgument(new Reference('security.token_storage'));
        $definition->setPublic(false);
        $container->setDefinition(self::ID, $definition);

        return array(null, self::ID, null);
    }
}
