<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Guard\Authenticator;

use Symphony\Component\Security\Guard\AbstractGuardAuthenticator;
use Symphony\Component\HttpFoundation\RedirectResponse;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\Security\Core\Exception\AuthenticationException;
use Symphony\Component\Security\Core\Security;
use Symphony\Component\Security\Http\Util\TargetPathTrait;

/**
 * A base class to make form login authentication easier!
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
abstract class AbstractFormLoginAuthenticator extends AbstractGuardAuthenticator
{
    use TargetPathTrait;

    /**
     * Return the URL to the login page.
     *
     * @return string
     */
    abstract protected function getLoginUrl();

    /**
     * Override to change what happens after a bad username/password is submitted.
     *
     * @return RedirectResponse
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        if ($request->hasSession()) {
            $request->getSession()->set(Security::AUTHENTICATION_ERROR, $exception);
        }

        $url = $this->getLoginUrl();

        return new RedirectResponse($url);
    }

    public function supportsRememberMe()
    {
        return true;
    }

    /**
     * Override to control what happens when the user hits a secure page
     * but isn't logged in yet.
     *
     * @return RedirectResponse
     */
    public function start(Request $request, AuthenticationException $authException = null)
    {
        $url = $this->getLoginUrl();

        return new RedirectResponse($url);
    }
}
