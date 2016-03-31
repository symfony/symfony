<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Guard\Authenticator;

use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

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
     * @param Request                 $request
     * @param AuthenticationException $exception
     *
     * @return RedirectResponse
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        $request->getSession()->set(Security::AUTHENTICATION_ERROR, $exception);
        $url = $this->getLoginUrl();

        return new RedirectResponse($url);
    }

    /**
     * Override to change what happens after successful authentication.
     *
     * @param Request        $request
     * @param TokenInterface $token
     * @param string         $providerKey
     *
     * @return RedirectResponse
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        @trigger_error(sprintf('The AbstractFormLoginAuthenticator::onAuthenticationSuccess() implementation was deprecated in Symfony 3.1 and will be removed in Symfony 4.0. You should implement this method yourself in %s and remove getDefaultSuccessRedirectUrl().', get_class($this)), E_USER_DEPRECATED);

        if (!method_exists($this, 'getDefaultSuccessRedirectUrl')) {
            throw new \Exception(sprintf('You must implement onAuthenticationSuccess() or getDefaultSuccessRedirectURL() in %s.', get_class($this)));
        }

        // if the user hits a secure page and start() was called, this was
        // the URL they were on, and probably where you want to redirect to
        $targetPath = $this->getTargetPath($request->getSession(), $providerKey);

        if (!$targetPath) {
            $targetPath = $this->getDefaultSuccessRedirectUrl();
        }

        return new RedirectResponse($targetPath);
    }

    public function supportsRememberMe()
    {
        return true;
    }

    /**
     * Override to control what happens when the user hits a secure page
     * but isn't logged in yet.
     *
     * @param Request                      $request
     * @param AuthenticationException|null $authException
     *
     * @return RedirectResponse
     */
    public function start(Request $request, AuthenticationException $authException = null)
    {
        $url = $this->getLoginUrl();

        return new RedirectResponse($url);
    }
}
