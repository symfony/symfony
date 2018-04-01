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
 * Handler for clearing invalidating the current session.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class SessionLogoutHandler implements LogoutHandlerInterface
{
    /**
     * Invalidate the current session.
     */
    public function logout(Request $request, Response $response, TokenInterface $token)
    {
        $request->getSession()->invalidate();
    }
}
