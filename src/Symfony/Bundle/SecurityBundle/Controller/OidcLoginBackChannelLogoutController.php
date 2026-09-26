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
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Receives the logout tokens an OIDC provider pushes to an oidc_login firewall and records
 * that the sessions they name have ended. The route loader declares a route for it at each
 * firewall's "backchannel_logout.path".
 *
 * The request comes from the provider and not from a browser: it carries no session, no
 * cookie and no CSRF token, and nothing of it is trusted until the token it carries is
 * verified (OpenID Connect Back-Channel Logout 1.0, Section 2.6).
 *
 * @see https://openid.net/specs/openid-connect-backchannel-1_0.html
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
final class OidcLoginBackChannelLogoutController
{
    /**
     * The one parameter of the request, Back-Channel Logout 1.0, Section 2.5.
     */
    private const PARAMETER = 'logout_token';

    /**
     * Section 2.7: nothing of this exchange is ever cached, whatever it answers.
     */
    private const HEADERS = [
        'Cache-Control' => 'no-cache, no-store',
        'Pragma' => 'no-cache',
    ];

    /**
     * @param ContainerInterface $backChannelLogouts OidcBackChannelLogout services, indexed by firewall name
     */
    public function __construct(
        private readonly ContainerInterface $backChannelLogouts,
    ) {
    }

    public function __invoke(Request $request, string $firewallName): Response
    {
        if (!$this->backChannelLogouts->has($firewallName)) {
            throw new NotFoundHttpException(\sprintf('Back-channel logout is not enabled for the "%s" firewall.', $firewallName));
        }

        $logoutToken = $request->request->get(self::PARAMETER);

        if (!\is_string($logoutToken) || '' === $logoutToken) {
            return self::error(\sprintf('The request carries no "%s" parameter.', self::PARAMETER));
        }

        try {
            $this->backChannelLogouts->get($firewallName)->logout($logoutToken);
        } catch (AuthenticationException $e) {
            // Section 2.8: a 400 tells the provider not to try this token again
            return self::error($e->getMessage());
        }

        // Section 2.7: nothing is said back
        return new Response('', Response::HTTP_OK, self::HEADERS);
    }

    private static function error(string $description): JsonResponse
    {
        // Section 2.9, in the shape RFC 6749, Section 5.2 gives a token endpoint error
        return new JsonResponse([
            'error' => 'invalid_request',
            'error_description' => $description,
        ], Response::HTTP_BAD_REQUEST, self::HEADERS);
    }
}
