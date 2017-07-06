<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\DependencyInjection\Fixtures\Factory;

use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\SecurityFactoryInterface;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @author John Kleijn <john@kleijnweb.nl>
 */
class SomePostAuthenticationFactory implements SecurityFactoryInterface
{
    const DUMMY_ID = 'security.authentication.listener.some_dummy_post_authentication_plugin';

    public function getPosition()
    {
        return 'post_authentication';
    }

    public function getKey()
    {
        return 'some_post_authentication_plugin';
    }

    public function addConfiguration(NodeDefinition $node)
    {
        //NOOP
    }

    public function create(ContainerBuilder $container, $id, $config, $userProvider, $defaultEntryPoint)
    {
        $container->setDefinition(self::DUMMY_ID, new Definition('\stdClass'));

        return array(null, self::DUMMY_ID, null);
    }
}
