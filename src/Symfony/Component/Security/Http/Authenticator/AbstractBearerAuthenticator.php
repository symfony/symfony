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

use Exception;
use Lcobucci\JWT\Token;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\TokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\TokenPassport;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\TokenExtractor\BearerTokenExtractorInterface;

/**
 * @author Vincent Chalamon <vincentchalamon@gmail.com>
 */
abstract class AbstractBearerAuthenticator implements AuthenticatorInterface, AuthenticationEntryPointInterface
{
    protected UserProviderInterface $userProvider;

    protected BearerTokenExtractorInterface $tokenExtractor;

    protected string $realmName;

    protected string $payloadKey;

    protected LoggerInterface $logger;

    public function __construct(
        UserProviderInterface $userProvider,
        BearerTokenExtractorInterface $tokenExtractor,
        string $realmName,
        string $payloadKey,
        LoggerInterface $logger = null
    ) {
        if (!interface_exists(Token::class)) {
            throw new RuntimeException(sprintf('"%s" requires lcobucci/jwt, please run "composer require lcobucci/jwt" to install it.', self::class));
        }

        $this->userProvider = $userProvider;
        $this->tokenExtractor = $tokenExtractor;
        $this->realmName = $realmName;
        $this->payloadKey = $payloadKey;
        $this->logger = $logger;
    }

    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        $response = new Response();
        $response->headers->set('WWW-Authenticate', sprintf('Bearer realm="%s"', $this->realmName));
        $response->setStatusCode(Response::HTTP_UNAUTHORIZED);

        return $response;
    }

    public function supports(Request $request): ?bool
    {
        return $this->tokenExtractor->supports($request);
    }

    public function authenticate(Request $request): Passport
    {
        try {
            $token = $this->getToken($this->tokenExtractor->extract($request));
        } catch (Exception $exception) {
            throw new AuthenticationException($exception->getMessage(), $exception->getCode(), $exception);
        }

        $userIdentifier = $token->claims()->get($this->payloadKey);
        if (null === $userIdentifier) {
            throw new AuthenticationException(sprintf('Cannot retrieve key "%s" from token.', $this->payloadKey));
        }

        return new TokenPassport(
            new TokenBadge($token, $userIdentifier, [$this->userProvider, 'loadUserByIdentifier'])
        );
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        return new PostAuthenticationToken($passport->getUser(), $firewallName, $passport->getUser()->getRoles());
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        if (null !== $this->logger) {
            $this->logger->info('Bearer authentication failed for token.', ['token' => $this->tokenExtractor->extract($request), 'exception' => $exception]);
        }

        return $this->start($request, $exception);
    }

    abstract protected function getToken(string $data): Token;
}
