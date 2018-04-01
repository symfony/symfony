<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Http\Logout;

use Symphony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symphony\Component\HttpFoundation\Response;
use Symphony\Component\HttpFoundation\Request;

/**
 * This handler clears the passed cookies when a user logs out.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
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
            $response->headers->clearCookie($cookieName, $cookieData['path'], $cookieData['domain']);
        }
    }
}
