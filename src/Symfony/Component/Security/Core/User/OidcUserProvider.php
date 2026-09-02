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

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

/**
 * User provider for the OIDC users built from the claims returned by an OIDC provider.
 *
 * OIDC users are self-contained: the user is built from the claims collected during
 * authentication, and returned as-is when the session is refreshed, without any
 * external lookup.
 *
 * The claims cannot define the security identity nor grant roles: the identifier comes
 * from the verified "sub" claim, and roles default to ROLE_USER. Use your own user
 * provider to map claims onto roles, or to load the users from your own storage.
 *
 * This user provider cannot be used with the "switch_user" feature or any other feature that
 * loads a user by its identifier alone (e.g. impersonation), as it requires the claims
 * collected during authentication. Use your own user provider instead.
 *
 * @implements AttributesBasedUserProviderInterface<OidcUser>
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class OidcUserProvider implements AttributesBasedUserProviderInterface
{
    /**
     * @param array $attributes The claims collected by the OIDC authenticator
     */
    public function loadUserByIdentifier(string $identifier, array $attributes = []): OidcUser
    {
        if (!\is_string($attributes['sub'] ?? null) || '' === $attributes['sub']) {
            $exception = new UserNotFoundException(\sprintf('The "%s" provider cannot load a user by its identifier alone: OIDC users are built from the claims collected during authentication.', self::class));
            $exception->setUserIdentifier($identifier);

            throw $exception;
        }

        // the identifier is the one the caller validated, which the OIDC authenticator reads
        // from the claim its firewall configures, and not a claim picked from the attributes
        return OidcUser::fromClaims(['user_identifier' => $identifier] + $this->filterPrivilegedClaims($attributes));
    }

    public function refreshUser(UserInterface $user): OidcUser
    {
        if (!$user instanceof OidcUser) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', $user::class));
        }

        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return OidcUser::class === $class;
    }

    /**
     * Drops any claim that would be mapped onto a security-sensitive OidcUser
     * constructor argument, so the provider cannot populate "roles" or override
     * the "userIdentifier" through the claims it returns (privilege escalation).
     *
     * The claim name is reduced to its letters and digits, lower-cased, to also
     * catch the separator and case variants that {@see OidcUser::fromClaims()}
     * would camel-case onto those arguments. Kept free of the optional
     * symfony/string dependency on purpose.
     *
     * @param array<string, mixed> $claims
     *
     * @return array<string, mixed>
     */
    private function filterPrivilegedClaims(array $claims): array
    {
        foreach (array_keys($claims) as $claim) {
            $normalized = strtolower((string) preg_replace('/[^a-zA-Z0-9]++/', '', (string) $claim));
            if ('roles' === $normalized || 'useridentifier' === $normalized) {
                unset($claims[$claim]);
            }
        }

        return $claims;
    }
}
