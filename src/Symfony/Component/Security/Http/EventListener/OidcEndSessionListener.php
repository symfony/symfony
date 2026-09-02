<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\Oidc\OidcDiscovery;

/**
 * Handles RP-Initiated Logout by redirecting to the OIDC provider's
 * end_session_endpoint on logout.
 *
 * @see https://openid.net/specs/openid-connect-rpinitiated-1_0.html
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class OidcEndSessionListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly OidcDiscovery $discovery,
        private readonly HttpUtils $httpUtils,
        private readonly ?string $postLogoutRedirectPath = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function onLogout(LogoutEvent $event): void
    {
        // a listener registered earlier already decided the logout response, and
        // whoever sets it first wins, as for the default logout response
        if (null !== $event->getResponse()) {
            return;
        }

        $token = $event->getToken();
        if (null === $token || !$token->hasAttribute('oidc_id_token')) {
            return;
        }

        $idToken = $token->getAttribute('oidc_id_token');
        if (!\is_string($idToken)) {
            return;
        }

        try {
            $endSessionEndpoint = $this->discovery->getSecureEndpoint('end_session_endpoint');
        } catch (AuthenticationException $e) {
            // logging out of the application must not depend on the provider being
            // reachable and correctly configured, so the logout stays a local one
            $this->logger?->warning('The OIDC provider does not announce a usable "end_session_endpoint"; falling back to a local logout.', ['exception' => $e]);

            return;
        }

        $params = ['id_token_hint' => $idToken];

        if (null !== $this->postLogoutRedirectPath) {
            $params['post_logout_redirect_uri'] = $this->httpUtils->generateUri($event->getRequest(), $this->postLogoutRedirectPath);
        }

        $endSessionUrl = $endSessionEndpoint
            .(str_contains($endSessionEndpoint, '?') ? '&' : '?')
            .http_build_query($params, '', '&', \PHP_QUERY_RFC3986);

        $event->setResponse(new RedirectResponse($endSessionUrl));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // before the default logout listener at priority 64, whose response this
            // one replaces when RP-Initiated Logout is enabled
            LogoutEvent::class => ['onLogout', 65],
        ];
    }
}
