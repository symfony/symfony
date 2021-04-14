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

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
interface AuthenticatorFactoryInterface
{
    /**
     * Creates the authenticator service(s) for the provided configuration.
     *
     * @return string|string[] The authenticator service ID(s) to be used by the firewall
     */
    public function createAuthenticator(ContainerBuilder $container, string $firewallName, array $config, string $userProviderId);
}
