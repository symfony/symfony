<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\AccessToken\Oidc;

use Symfony\Component\Security\Core\User\OidcUser;

/**
 * Creates {@see OidcUser} from claims and validates the endpoints advertised by the discovery document.
 *
 * @internal
 */
trait OidcTrait
{
    private function createUser(array $claims): OidcUser
    {
        return OidcUser::fromClaims($claims);
    }

    /**
     * The discovery specification requires the endpoints it advertises to use the "https" scheme.
     * They are rejected here when they downgrade the transport that carried the discovery document.
     */
    private static function checkDiscoveredEndpoint(mixed $endpoint, string $key, ?string $discoveryUrl): string
    {
        if (!\is_string($endpoint) || '' === $endpoint) {
            throw new \RuntimeException(\sprintf('The "%s" is missing from the OIDC discovery document.', $key));
        }

        $scheme = parse_url($endpoint, \PHP_URL_SCHEME);

        if ($scheme && 'https' !== strtolower($scheme) && str_starts_with($discoveryUrl ?? '', 'https://')) {
            throw new \RuntimeException(\sprintf('The "%s" found in the OIDC discovery document must use the "https" scheme, "%s" given.', $key, $scheme));
        }

        return $endpoint;
    }
}
