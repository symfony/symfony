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
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\ParameterBagUtils;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 * @experimental in 5.1
 */
class FormLoginAuthenticator extends AbstractLoginFormAuthenticator implements PasswordAuthenticatedInterface, CsrfProtectedAuthenticatorInterface
{
    private $httpUtils;
    private $userProvider;
    private $successHandler;
    private $failureHandler;
    private $options;

    public function __construct(HttpUtils $httpUtils, UserProviderInterface $userProvider, AuthenticationSuccessHandlerInterface $successHandler, AuthenticationFailureHandlerInterface $failureHandler, array $options)
    {
        $this->httpUtils = $httpUtils;
        $this->userProvider = $userProvider;
        $this->successHandler = $successHandler;
        $this->failureHandler = $failureHandler;
        $this->options = array_merge([
            'username_parameter' => '_username',
            'password_parameter' => '_password',
            'check_path' => '/login_check',
            'post_only' => true,

            'csrf_parameter' => '_csrf_token',
            'csrf_token_id' => 'authenticate',
        ], $options);
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->httpUtils->generateUri($request, $this->options['login_path']);
    }

    public function supports(Request $request): bool
    {
        return ($this->options['post_only'] ? $request->isMethod('POST') : true)
            && $this->httpUtils->checkRequestPath($request, $this->options['check_path']);
    }

    public function getCredentials(Request $request): array
    {
        $credentials = [];
        $credentials['csrf_token'] = ParameterBagUtils::getRequestParameterValue($request, $this->options['csrf_parameter']);

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

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $providerKey): ?Response
    {
        return $this->successHandler->onAuthenticationSuccess($request, $token);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return $this->failureHandler->onAuthenticationFailure($request, $exception);
    }
}
