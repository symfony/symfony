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
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * SecurityFactoryInterface is the interface for all security authentication listener.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface SecurityFactoryInterface
{
    /**
     * Configures the container services required to use the authentication listener.
     *
     * @param ContainerBuilder $container
     * @param string           $id                The unique id of the firewall 
     * @param array            $config            The options array for the listener
     * @param string           $userProvider      The service id of the user provider
     * @param string           $defaultEntryPoint
     *
     * @return array containing three values:
     *               - the provider id
     *               - the listener id
     *               - the entry point id
     */
    public function create(ContainerBuilder $container, $id, $config, $userProvider, $defaultEntryPoint);

    /**
     * Defines the position at which the provider is called.
     * Possible values: pre_auth, form, http, and remember_me.
     *
     * @return string
     */
    public function getPosition();

    /**
     * Defines the configuration key used to reference the provider
     * in the firewall configuration.
     * 
     * @return string
     */
    public function getKey();

    public function addConfiguration(NodeDefinition $builder);
}
