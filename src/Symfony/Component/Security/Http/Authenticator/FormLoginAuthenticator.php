<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\ParameterBagUtils;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 * @experimental in 5.1
 */
class FormLoginAuthenticator extends AbstractLoginFormAuthenticator implements PasswordAuthenticatedInterface, CsrfProtectedAuthenticatorInterface
{
    use TargetPathTrait;

    private $options;
    private $httpUtils;
    private $csrfTokenManager;
    private $userProvider;

    public function __construct(HttpUtils $httpUtils, ?CsrfTokenManagerInterface $csrfTokenManager, UserProviderInterface $userProvider, array $options)
    {
        $this->httpUtils = $httpUtils;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->options = array_merge([
            'username_parameter' => '_username',
            'password_parameter' => '_password',
            'csrf_parameter' => '_csrf_token',
            'csrf_token_id' => 'authenticate',
            'post_only' => true,

            'always_use_default_target_path' => false,
            'default_target_path' => '/',
            'login_path' => '/login',
            'target_path_parameter' => '_target_path',
            'use_referer' => false,
        ], $options);
        $this->userProvider = $userProvider;
    }

    protected function getLoginUrl(): string
    {
        return $this->options['login_path'];
    }

    public function supports(Request $request): bool
    {
        return ($this->options['post_only'] ? $request->isMethod('POST') : true)
            && $this->httpUtils->checkRequestPath($request, $this->options['check_path']);
    }

    public function getCredentials(Request $request): array
    {
        $credentials = [];

        if (null !== $this->csrfTokenManager) {
            $credentials['csrf_token'] = ParameterBagUtils::getRequestParameterValue($request, $this->options['csrf_parameter']);
        }

        if ($this->options['post_only']) {
            $credentials['username'] = ParameterBagUtils::getParameterBagValue($request->request, $this->options['username_parameter']);
            $credentials['password'] = ParameterBagUtils::getParameterBagValue($request->request, $this->options['password_parameter']);
        } else {
            $credentials['username'] = ParameterBagUtils::getRequestParameterValue($request, $this->options['username_parameter']);
            $credentials['password'] = ParameterBagUtils::getRequestParameterValue($request, $this->options['password_parameter']);
        }

        if (!\is_string($credentials['username']) && (!\is_object($credentials['username']) || !method_exists($credentials['username'], '__toString'))) {
            throw new BadRequestHttpException(sprintf('The key "%s" must be a string, "%s" given.', $this->options['username_parameter'], \gettype($credentials['username'])));
        }

        $credentials['username'] = trim($credentials['username']);

        if (\strlen($credentials['username']) > Security::MAX_USERNAME_LENGTH) {
            throw new BadCredentialsException('Invalid username.');
        }

        $request->getSession()->set(Security::LAST_USERNAME, $credentials['username']);

        return $credentials;
    }

    public function getPassword($credentials): ?string
    {
        return $credentials['password'];
    }

    public function getUser($credentials): ?UserInterface
    {
        return $this->userProvider->loadUserByUsername($credentials['username']);
    }

    public function getCsrfTokenId(): string
    {
        return $this->options['csrf_token_id'];
    }

    public function getCsrfToken($credentials): ?string
    {
        return $credentials['csrf_token'];
    }

    public function createAuthenticatedToken(UserInterface $user, $providerKey): TokenInterface
    {
        return new UsernamePasswordToken($user, null, $providerKey, $user->getRoles());
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $providerKey): Response
    {
        return $this->httpUtils->createRedirectResponse($request, $this->determineTargetUrl($request, $providerKey));
    }

    private function determineTargetUrl(Request $request, string $providerKey)
    {
        if ($this->options['always_use_default_target_path']) {
            return $this->options['default_target_path'];
        }

        if ($targetUrl = ParameterBagUtils::getRequestParameterValue($request, $this->options['target_path_parameter'])) {
            return $targetUrl;
        }

        if ($targetUrl = $this->getTargetPath($request->getSession(), $providerKey)) {
            $this->removeTargetPath($request->getSession(), $providerKey);

            return $targetUrl;
        }

        if ($this->options['use_referer'] && $targetUrl = $request->headers->get('Referer')) {
            if (false !== $pos = strpos($targetUrl, '?')) {
                $targetUrl = substr($targetUrl, 0, $pos);
            }
            if ($targetUrl && $targetUrl !== $this->httpUtils->generateUri($request, $this->options['login_path'])) {
                return $targetUrl;
            }
        }

        return $this->options['default_target_path'];
    }
}
