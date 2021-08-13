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
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PreAuthenticatedUserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * The base authenticator for authenticators to use pre-authenticated
 * requests (e.g. using certificates).
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @internal
 */
abstract class AbstractPreAuthenticatedAuthenticator implements InteractiveAuthenticatorInterface
{
    private $userProvider;
    private $tokenStorage;
    private $firewallName;
    private $logger;

    public function __construct(UserProviderInterface $userProvider, TokenStorageInterface $tokenStorage, string $firewallName, LoggerInterface $logger = null)
    {
        $this->userProvider = $userProvider;
        $this->tokenStorage = $tokenStorage;
        $this->firewallName = $firewallName;
        $this->logger = $logger;
    }

    /**
     * Returns the username of the pre-authenticated user.
     *
     * This authenticator is skipped if null is returned or a custom
     * BadCredentialsException is thrown.
     */
    abstract protected function extractUsername(Request $request): ?string;

    public function supports(Request $request): ?bool
    {
        try {
            $username = $this->extractUsername($request);
        } catch (BadCredentialsException $e) {
            $this->clearToken($e);

            if (null !== $this->logger) {
                $this->logger->debug('Skipping pre-authenticated authenticator as a BadCredentialsException is thrown.', ['exception' => $e, 'authenticator' => static::class]);
            }

            return false;
        }

        if (null === $username) {
            if (null !== $this->logger) {
                $this->logger->debug('Skipping pre-authenticated authenticator no username could be extracted.', ['authenticator' => static::class]);
            }

            return false;
        }

        $request->attributes->set('_pre_authenticated_username', $username);

        return true;
    }

    public function authenticate(Request $request): PassportInterface
    {
        // @deprecated since Symfony 5.3, change to $this->userProvider->loadUserByIdentifier() in 6.0
        $method = 'loadUserByIdentifier';
        if (!method_exists($this->userProvider, 'loadUserByIdentifier')) {
            trigger_deprecation('symfony/security-core', '5.3', 'Not implementing method "loadUserByIdentifier()" in user provider "%s" is deprecated. This method will replace "loadUserByUsername()" in Symfony 6.0.', get_debug_type($this->userProvider));

            $method = 'loadUserByUsername';
        }

        return new SelfValidatingPassport(
            new UserBadge($request->attributes->get('_pre_authenticated_username'), [$this->userProvider, $method]),
            [new PreAuthenticatedUserBadge()]
        );
    }

    public function createAuthenticatedToken(PassportInterface $passport, string $firewallName): TokenInterface
    {
        return new PreAuthenticatedToken($passport->getUser(), null, $firewallName, $passport->getUser()->getRoles());
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null; // let the original request continue
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $this->clearToken($exception);

        return null;
    }

    public function isInteractive(): bool
    {
        return true;
    }

    private function clearToken(AuthenticationException $exception): void
    {
        $token = $this->tokenStorage->getToken();
        if ($token instanceof PreAuthenticatedToken && $this->firewallName === $token->getFirewallName()) {
            $this->tokenStorage->setToken(null);

            if (null !== $this->logger) {
                $this->logger->info('Cleared pre-authenticated token due to an exception.', ['exception' => $exception]);
            }
        }
    }
}
