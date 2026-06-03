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
 * Stateless authenticators are authenticators that can work without a user provider.
 *
 * This situation can only occur in stateless firewalls, as statefull firewalls
 * need the user provider to refresh the user in each subsequent request. A
 * stateless authenticator can be used on both stateless and statefull authenticators.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
interface StatelessAuthenticatorFactoryInterface extends AuthenticatorFactoryInterface
{
    public function createAuthenticator(ContainerBuilder $container, string $firewallName, array $config, ?string $userProviderId): string|array;
}
