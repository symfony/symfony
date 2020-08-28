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

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\AuthenticationServiceException;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PasswordUpgradeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 * @experimental in 5.2
 */
class HttpBasicAuthenticator implements AuthenticatorInterface, AuthenticationEntryPointInterface
{
    private $realmName;
    private $userProvider;
    private $logger;

    public function __construct(string $realmName, UserProviderInterface $userProvider, ?LoggerInterface $logger = null)
    {
        $this->realmName = $realmName;
        $this->userProvider = $userProvider;
        $this->logger = $logger;
    }

    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        $response = new Response();
        $response->headers->set('WWW-Authenticate', sprintf('Basic realm="%s"', $this->realmName));
        $response->setStatusCode(401);

        return $response;
    }

    public function supports(Request $request): ?bool
    {
        return $request->headers->has('PHP_AUTH_USER');
    }

    public function authenticate(Request $request): PassportInterface
    {
        $username = $request->headers->get('PHP_AUTH_USER');
        $password = $request->headers->get('PHP_AUTH_PW', '');

        $passport = new Passport(new UserBadge($username, function ($username) {
            $user = $this->userProvider->loadUserByUsername($username);
            if (!$user instanceof UserInterface) {
                throw new AuthenticationServiceException('The user provider must return a UserInterface object.');
            }

            return $user;
        }), new PasswordCredentials($password));
        if ($this->userProvider instanceof PasswordUpgraderInterface) {
            $passport->addBadge(new PasswordUpgradeBadge($password, $this->userProvider));
        }

        return $passport;
    }

    /**
     * @param Passport $passport
     */
    public function createAuthenticatedToken(PassportInterface $passport, string $firewallName): TokenInterface
    {
        return new UsernamePasswordToken($passport->getUser(), null, $firewallName, $passport->getUser()->getRoles());
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        if (null !== $this->logger) {
            $this->logger->info('Basic authentication failed for user.', ['username' => $request->headers->get('PHP_AUTH_USER'), 'exception' => $exception]);
        }

        return $this->start($request, $exception);
    }
}
