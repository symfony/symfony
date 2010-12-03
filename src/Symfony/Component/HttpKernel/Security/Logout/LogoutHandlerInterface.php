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
     * 
     * @param Request $request
     * @param Response $response
     * @param TokenInterface $token
     * @return void
     */
    function logout(Request $request, Response $response, TokenInterface $token);
}