<?php

namespace Symfony\Component\HttpKernel\Security\Logout;

use Symfony\Component\Security\Authentication\Token\TokenInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Handler for clearing invalidating the current session.
 * 
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class SessionLogoutHandler implements LogoutHandlerInterface
{
    /**
     * Invalidate the current session
     * 
     * @param Request $request
     * @param Response $response
     * @param TokenInterface $token
     * @return void
     */
    public function logout(Request $request, Response $response, TokenInterface $token)
    {
        $request->getSession()->invalidate();
    }    
}