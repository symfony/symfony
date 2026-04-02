<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator\Oidc;

/**
 * Holds the parsed OpenID Connect discovery configuration.
 *
 * @see https://openid.net/specs/openid-connect-discovery-1_0.html
 *
 * @author Mathieu Music <music.music@gmail.com>
 */
final class OidcConfiguration
{
    public function __construct(
        public readonly string $authorizationEndpoint,
        public readonly string $tokenEndpoint,
        public readonly string $issuer,
        public readonly string $jwksUri,
        public readonly ?string $userinfoEndpoint = null,
        public readonly ?string $endSessionEndpoint = null,
        public readonly array $codeChallengeMethodsSupported = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data The decoded .well-known/openid-configuration response
     */
    public static function fromArray(array $data): self
    {
        foreach (['authorization_endpoint', 'token_endpoint', 'issuer', 'jwks_uri'] as $required) {
            if (!isset($data[$required]) || !\is_string($data[$required]) || '' === $data[$required]) {
                throw new \InvalidArgumentException(\sprintf('The OIDC discovery document must contain a non-empty "%s" field.', $required));
            }
        }

        return new self(
            authorizationEndpoint: $data['authorization_endpoint'],
            tokenEndpoint: $data['token_endpoint'],
            issuer: $data['issuer'],
            jwksUri: $data['jwks_uri'],
            userinfoEndpoint: $data['userinfo_endpoint'] ?? null,
            endSessionEndpoint: $data['end_session_endpoint'] ?? null,
            codeChallengeMethodsSupported: $data['code_challenge_methods_supported'] ?? [],
        );
    }
}
