<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Controller;

use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Starts the OIDC Authorization Code Flow of an oidc_login firewall by redirecting
 * to the provider, so that a page can link to it, e.g. the login page of a firewall
 * offering several ways to log in. The route loader declares a route for it at each
 * firewall's "start_path".
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class OidcLoginStartController
{
    /**
     * @param ContainerInterface $authenticators oidc_login authenticators, indexed by firewall name
     */
    public function __construct(
        private readonly ContainerInterface $authenticators,
    ) {
    }

    public function __invoke(Request $request, string $firewallName): Response
    {
        if (!$this->authenticators->has($firewallName)) {
            throw new NotFoundHttpException(\sprintf('No "oidc_login" authenticator is registered for the "%s" firewall.', $firewallName));
        }

        return $this->authenticators->get($firewallName)->start($request);
    }
}
