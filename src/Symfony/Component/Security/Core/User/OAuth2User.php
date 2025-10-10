<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\User;

/**
 * UserInterface implementation used by the access-token security workflow with an OIDC server.
 */
class OAuth2User implements UserInterface
{
    public readonly array $additionalClaims;

    public function __construct(
        private array $roles = ['ROLE_USER'],
        // Standard Claims (https://datatracker.ietf.org/doc/html/rfc7662#section-2.2)
        public readonly ?string $scope = null,
        public readonly ?string $clientId = null,
        public readonly ?string $username = null,
        public readonly ?string $tokenType = null,
        public readonly ?int $exp = null,
        public readonly ?int $iat = null,
        public readonly ?int $nbf = null,
        public readonly ?string $sub = null,
        public readonly ?string $aud = null,
        public readonly ?string $iss = null,
        public readonly ?string $jti = null,

        // Additional Claims ("
        //    Specific implementations MAY extend this structure with
        //    their own service-specific response names as top-level members
        //    of this JSON object.
        // ")
        ...$additionalClaims,
    ) {
        if ((null === $sub || '' === $sub) && (null === $username || '' === $username)) {
            throw new \InvalidArgumentException('The claim "sub" or "username" must be provided.');
        }

        $this->additionalClaims = $additionalClaims['additionalClaims'] ?? $additionalClaims;
    }

    /**
     * OIDC or OAuth specs don't have any "role" notion.
     *
     * If you want to implement "roles" from your OIDC server,
     * send a "roles" constructor argument to this object
     * (e.g.: using a custom UserProvider).
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getUserIdentifier(): string
    {
        return (string) ($this->sub ?? $this->username);
    }

    public function eraseCredentials(): void
    {
    }
}
