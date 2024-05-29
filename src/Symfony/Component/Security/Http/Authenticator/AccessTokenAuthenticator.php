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
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\AccessToken\AccessTokenExtractorInterface;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Provides an implementation of the RFC6750 of an authentication via
 * an access token.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
class AccessTokenAuthenticator implements AuthenticatorInterface
{
    private ?TranslatorInterface $translator = null;

    public function __construct(
        private readonly AccessTokenHandlerInterface $accessTokenHandler,
        private readonly AccessTokenExtractorInterface $accessTokenExtractor,
        private readonly ?UserProviderInterface $userProvider = null,
        private readonly ?AuthenticationSuccessHandlerInterface $successHandler = null,
        private readonly ?AuthenticationFailureHandlerInterface $failureHandler = null,
        private readonly ?string $realm = null,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return null === $this->accessTokenExtractor->extractAccessToken($request) ? false : null;
    }

    public function authenticate(Request $request): Passport
    {
        $accessToken = $this->accessTokenExtractor->extractAccessToken($request);
        if (!$accessToken) {
            throw new BadCredentialsException('Invalid credentials.');
        }

        $userBadge = $this->accessTokenHandler->getUserBadgeFrom($accessToken);
        if ($this->userProvider && (null === $userBadge->getUserLoader() || $userBadge->getUserLoader() instanceof FallbackUserLoader)) {
            $userBadge->setUserLoader($this->userProvider->loadUserByIdentifier(...));
        }

        return new SelfValidatingPassport($userBadge);
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        return new PostAuthenticationToken($passport->getUser(), $firewallName, $passport->getUser()->getRoles());
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return $this->successHandler?->onAuthenticationSuccess($request, $token);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if (null !== $this->failureHandler) {
            return $this->failureHandler->onAuthenticationFailure($request, $exception);
        }

        if (null !== $this->translator) {
            $errorMessage = $this->translator->trans($exception->getMessageKey(), $exception->getMessageData(), 'security');
        } else {
            $errorMessage = strtr($exception->getMessageKey(), $exception->getMessageData());
        }

        return new Response(
            null,
            Response::HTTP_UNAUTHORIZED,
            ['WWW-Authenticate' => $this->getAuthenticateHeader($errorMessage)]
        );
    }

    public function setTranslator(?TranslatorInterface $translator): void
    {
        $this->translator = $translator;
    }

    /**
     * @see https://datatracker.ietf.org/doc/html/rfc6750#section-3
     */
    private function getAuthenticateHeader(?string $errorDescription = null): string
    {
        $data = [
            'realm' => $this->realm,
            'error' => 'invalid_token',
            'error_description' => $errorDescription,
        ];
        $values = [];
        foreach ($data as $k => $v) {
            if (null === $v || '' === $v) {
                continue;
            }
            $values[] = sprintf('%s="%s"', $k, $v);
        }

        return sprintf('Bearer %s', implode(',', $values));
    }
}
