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

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Attribute\FirewallListenerOrder;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcTokenRefresher;
use Symfony\Component\Security\Http\Exception\OidcInvalidGrantException;

/**
 * Renews the OIDC access token of the logged-in user before it expires.
 *
 * It runs after the firewall restored the security token from the session, so the
 * renewed tokens are the ones the request and the rest of the session see. Failing
 * to renew only ends the session when the provider says the refresh token is gone:
 * a provider that cannot be reached would otherwise log every user out at once.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
#[FirewallListenerOrder(after: ContextListener::class)]
final class OidcTokenRefreshListener extends AbstractListener
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly OidcTokenRefresher $refresher,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Always defers to authenticate(), the way ContextListener does.
     *
     * A lazy firewall calls supports() on every listener before it installs the token
     * storage initializer, so the security token is not restored yet: deciding here
     * whether there is a refresh token to use would evict this listener from the chain
     * on every lazy firewall, which is what the recipe configures. Returning null keeps
     * the firewall lazy, and authenticate() is the one that checks the security token.
     */
    public function supports(Request $request): ?bool
    {
        return null;
    }

    public function authenticate(RequestEvent $event): void
    {
        if (null === $token = $this->tokenStorage->getToken()) {
            return;
        }

        try {
            if ($this->refresher->refreshIfNeeded($token)) {
                $this->logger?->debug('Renewed the OIDC access token of the logged-in user.');
            }
        } catch (OidcInvalidGrantException $e) {
            $this->logger?->info('The OIDC provider no longer honors the refresh token; logging the user out.', ['exception' => $e]);

            $this->tokenStorage->setToken(null);
        } catch (AuthenticationException $e) {
            // the renewal is tried again on the next request, with the tokens left untouched
            $this->logger?->warning('Could not renew the OIDC access token of the logged-in user.', ['exception' => $e]);
        }
    }
}
