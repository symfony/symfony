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
 * Holds the tokens returned by the OIDC provider's token endpoint.
 *
 * @author Mathieu Music <music.music@gmail.com>
 */
final class OidcTokens
{
    public function __construct(
        public readonly string $accessToken,
        public readonly string $idToken,
        public readonly ?string $refreshToken = null,
        public readonly ?int $expiresIn = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data The decoded token endpoint JSON response
     */
    public static function fromTokenEndpointResponse(array $data): self
    {
        if (!isset($data['access_token'], $data['id_token'])) {
            throw new \InvalidArgumentException('The token endpoint response must contain "access_token" and "id_token".');
        }

        return new self(
            accessToken: $data['access_token'],
            idToken: $data['id_token'],
            refreshToken: $data['refresh_token'] ?? null,
            expiresIn: isset($data['expires_in']) ? (int) $data['expires_in'] : null,
        );
    }
}
