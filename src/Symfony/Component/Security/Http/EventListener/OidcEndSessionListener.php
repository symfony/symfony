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

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcClient;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcTokens;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\HttpUtils;

/**
 * Handles RP-Initiated Logout by redirecting to the OIDC provider's
 * end_session_endpoint on logout.
 *
 * @see https://openid.net/specs/openid-connect-rpinitiated-1_0.html
 *
 * @author Mathieu Music <music.music@gmail.com>
 */
final class OidcEndSessionListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly OidcClient $oidcClient,
        private readonly HttpUtils $httpUtils,
        private readonly ?string $postLogoutRedirectPath = null,
    ) {
    }

    public function onLogout(LogoutEvent $event): void
    {
        $token = $event->getToken();
        if (null === $token) {
            return;
        }

        $oidcTokens = $token->getAttribute('oidc_tokens');
        if (!$oidcTokens instanceof OidcTokens) {
            return;
        }

        $postLogoutRedirectUri = null;
        if (null !== $this->postLogoutRedirectPath) {
            $postLogoutRedirectUri = $this->httpUtils->generateUri($event->getRequest(), $this->postLogoutRedirectPath);
        }

        $endSessionUrl = $this->oidcClient->buildEndSessionUrl($oidcTokens->idToken, $postLogoutRedirectUri);
        $event->setResponse(new RedirectResponse($endSessionUrl));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LogoutEvent::class => 'onLogout',
        ];
    }
}
