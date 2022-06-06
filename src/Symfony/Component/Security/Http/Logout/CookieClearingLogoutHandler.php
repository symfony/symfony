<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Logout;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\EventListener\CookieClearingLogoutListener;

trigger_deprecation('symfony/security-http', '5.4', 'The "%s" class is deprecated, use "%s" instead.', CookieClearingLogoutHandler::class, CookieClearingLogoutListener::class);

/**
 * This handler clears the passed cookies when a user logs out.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * @deprecated since Symfony 5.4, use {@link CookieClearingLogoutListener} instead
 */
class CookieClearingLogoutHandler implements LogoutHandlerInterface
{
    private $cookies;

    /**
     * @param array $cookies An array of cookie names to unset
     */
    public function __construct(array $cookies)
    {
        $this->cookies = $cookies;
    }

    /**
     * Implementation for the LogoutHandlerInterface. Deletes all requested cookies.
     */
    public function logout(Request $request, Response $response, TokenInterface $token)
    {
        foreach ($this->cookies as $cookieName => $cookieData) {
            $response->headers->clearCookie($cookieName, $cookieData['path'], $cookieData['domain'], $cookieData['secure'] ?? false, true, $cookieData['samesite'] ?? null);
        }
    }
}
