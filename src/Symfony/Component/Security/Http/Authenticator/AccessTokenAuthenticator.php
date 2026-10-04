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
use Symfony\Component\Security\Http\AccessToken\SenderConstraintInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Component\Security\Http\EntryPoint\FallbackAuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Provides an implementation of the RFC6750 of an authentication via
 * an access token.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
class AccessTokenAuthenticator implements AuthenticatorInterface, FallbackAuthenticationEntryPointInterface
{
    /**
     * The token attribute holding the scopes the access token was granted, as a list of strings.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc6749#section-3.3
     */
    public const SCOPE_ATTRIBUTE = 'oauth2_scope';

    /**
     * The claims a scope is read from, in order of precedence: "scope" is the one RFC 9068 §2.2.3
     * and RFC 7662 §2.2 define, "scp" is the spelling some providers use instead.
     */
    private const SCOPE_CLAIMS = ['scope', 'scp'];

    private ?TranslatorInterface $translator = null;

    /**
     * @param string|null                    $resourceMetadataUri The URL of the RFC 9728 protected resource metadata document to advertise
     *                                                            in the "WWW-Authenticate" header; a path is resolved against the request
     * @param SenderConstraintInterface|null $senderConstraint    What the request has to prove possession of, beyond holding the access
     *                                                            token, for the token to be accepted (RFC 9449 for DPoP). Null accepts a
     *                                                            token from whoever presents it, which is what a bearer token is
     *                                                            (RFC 6750, Section 1)
     */
    public function __construct(
        private readonly AccessTokenHandlerInterface $accessTokenHandler,
        private readonly AccessTokenExtractorInterface $accessTokenExtractor,
        private readonly ?UserProviderInterface $userProvider = null,
        private readonly ?AuthenticationSuccessHandlerInterface $successHandler = null,
        private readonly ?AuthenticationFailureHandlerInterface $failureHandler = null,
        private readonly ?string $realm = null,
        private readonly ?string $resourceMetadataUri = null,
        private readonly ?SenderConstraintInterface $senderConstraint = null,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        if (null === $this->accessTokenExtractor->extractAccessToken($request)) {
            $request->attributes->get(SecurityRequestAttributes::UNSUPPORTED_REASONS)?->add(\sprintf('the "%s" extractor found no access token in the request', get_debug_type($this->accessTokenExtractor)));

            return false;
        }

        return null;
    }

    public function authenticate(Request $request): Passport
    {
        $accessToken = $this->accessTokenExtractor->extractAccessToken($request);
        if (!$accessToken) {
            throw new BadCredentialsException('Invalid credentials.');
        }

        $userBadge = $this->accessTokenHandler->getUserBadgeFrom($accessToken);

        // the token says what it is bound to, the request has to prove possession of it before anything
        // is loaded on its behalf
        $this->senderConstraint?->check($request, $accessToken, $userBadge->getAttributes() ?? []);

        if ($this->userProvider && (null === $userBadge->getUserLoader() || $userBadge->getUserLoader() instanceof FallbackUserLoader)) {
            $userBadge->setUserLoader($this->userProvider->loadUserByIdentifier(...));
        }

        return new SelfValidatingPassport($userBadge);
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        $token = new PostAuthenticationToken($passport->getUser(), $firewallName, $passport->getUser()->getRoles());
        $token->setAttribute(self::SCOPE_ATTRIBUTE, self::extractScopes($passport->getBadge(UserBadge::class)?->getAttributes() ?? []));

        return $token;
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
            ['WWW-Authenticate' => $this->getAuthenticateHeader($request, 'invalid_token', $errorMessage, $exception)]
        );
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        // RFC 6750, Section 3: the challenge of a request that carries no access token at all
        // holds no error code, as an error code describes a token the client did send. This is
        // the request an RFC 9728 client makes to discover where to get a token from.
        return new Response(
            null,
            Response::HTTP_UNAUTHORIZED,
            ['WWW-Authenticate' => $this->getAuthenticateHeader($request, null, null, $authException)]
        );
    }

    public function setTranslator(?TranslatorInterface $translator): void
    {
        $this->translator = $translator;
    }

    /**
     * @return string[]
     *
     * @see https://datatracker.ietf.org/doc/html/rfc6749#section-3.3
     */
    private static function extractScopes(array $claims): array
    {
        foreach (self::SCOPE_CLAIMS as $claim) {
            $scope = $claims[$claim] ?? null;
            if (\is_array($scope)) {
                $scope = implode(' ', array_filter($scope, \is_string(...)));
            }
            if (!\is_string($scope) || '' === trim($scope)) {
                continue;
            }

            return array_values(array_unique(preg_split('/\s+/', trim($scope))));
        }

        return [];
    }

    /**
     * @see https://datatracker.ietf.org/doc/html/rfc6750#section-3
     * @see https://datatracker.ietf.org/doc/html/rfc9728#section-5.1
     */
    private function getAuthenticateHeader(Request $request, ?string $error = null, ?string $errorDescription = null, ?AuthenticationException $exception = null): string
    {
        // the challenge of a firewall accepting sender-constrained tokens names the scheme they are
        // presented under, and the constraint adds what a client needs to come back with one (RFC 9449,
        // Section 7.1); a bearer firewall keeps the scheme and the parameters of RFC 6750, Section 3
        [$scheme, $parameters] = $this->senderConstraint?->getChallenge($exception) ?? ['Bearer', []];

        $data = [
            'realm' => $this->realm,
            'error' => $error,
            'error_description' => $errorDescription,
            'resource_metadata' => match (true) {
                null === $this->resourceMetadataUri => null,
                str_starts_with($this->resourceMetadataUri, '/') => $request->getUriForPath($this->resourceMetadataUri),
                default => $this->resourceMetadataUri,
            },
        ];
        $values = [];
        foreach (array_replace($data, $parameters) as $k => $v) {
            if (null === $v || '' === $v) {
                continue;
            }
            $values[] = \sprintf('%s="%s"', $k, $v);
        }

        return $values ? $scheme.' '.implode(',', $values) : $scheme;
    }
}
