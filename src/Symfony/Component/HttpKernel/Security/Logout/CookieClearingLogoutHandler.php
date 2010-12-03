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
 * This handler cleares the passed cookies when a user logs out.
 * 
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class CookieClearingLogoutHandler implements LogoutHandlerInterface
{
    protected $cookieNames;
    
    /**
     * Constructor
     * @param array $cookieNames An array of cookie names to unset
     */
    public function __construct(array $cookieNames)
    {
        $this->cookieNames = $cookieNames;
    }
    
    /**
     * Returns the names of the cookies to unset
     * @return array
     */
    public function getCookieNames()
    {
        return $this->cookieNames;
    }
    
    /**
     * Implementation for the LogoutHandlerInterface. Deletes all requested cookies.
     * 
     * @param Request $request
     * @param Response $response
     * @param TokenInterface $token
     * @return void
     */
    public function logout(Request $request, Response $response, TokenInterface $token)
    {
        $expires = time() - 86400;
        
        foreach ($this->cookieNames as $cookieName) {
            $response->headers->setCookie($cookieName, '', null, $expires);
        }
    }
}