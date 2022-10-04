<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Firewall;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Http\Authentication\AuthenticatorManagerInterface;

/**
 * Firewall authentication listener that delegates to the authenticator system.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
class AuthenticatorManagerListener extends AbstractListener
{
    private AuthenticatorManagerInterface $authenticatorManager;

    public function __construct(AuthenticatorManagerInterface $authenticationManager)
    {
        $this->authenticatorManager = $authenticationManager;
    }

    public function supports(Request $request): ?bool
    {
        return $this->authenticatorManager->supports($request);
    }

    public function authenticate(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $response = $this->authenticatorManager->authenticateRequest($request);
        if (null === $response) {
            return;
        }

        $event->setResponse($response);
    }
}
