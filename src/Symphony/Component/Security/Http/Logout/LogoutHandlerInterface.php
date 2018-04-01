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
 * Interface that needs to be implemented by LogoutHandlers.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface LogoutHandlerInterface
{
    /**
     * This method is called by the LogoutListener when a user has requested
     * to be logged out. Usually, you would unset session variables, or remove
     * cookies, etc.
     */
    public function logout(Request $request, Response $response, TokenInterface $token);
}
