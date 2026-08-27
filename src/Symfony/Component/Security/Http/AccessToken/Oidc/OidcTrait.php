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

use function Symfony\Component\String\u;

/**
 * Creates {@see OidcUser} from claims and validates the endpoints advertised by the discovery document.
 *
 * @internal
 */
trait OidcTrait
{
    private function createUser(array $claims): OidcUser
    {
        if (!\function_exists('Symfony\Component\String\u')) {
            throw new \LogicException('You cannot use the "OidcUserInfoTokenHandler" since the String component is not installed. Try running "composer require symfony/string".');
        }

        foreach ($claims as $claim => $value) {
            unset($claims[$claim]);
            if ('' === $value || null === $value) {
                continue;
            }
            $claims[u($claim)->camel()->toString()] = $value;
        }

        if (isset($claims['updatedAt']) && '' !== $claims['updatedAt']) {
            $claims['updatedAt'] = (new \DateTimeImmutable())->setTimestamp($claims['updatedAt']);
        }

        if (\array_key_exists('emailVerified', $claims) && null !== $claims['emailVerified'] && '' !== $claims['emailVerified']) {
            $claims['emailVerified'] = (bool) $claims['emailVerified'];
        }

        if (\array_key_exists('phoneNumberVerified', $claims) && null !== $claims['phoneNumberVerified'] && '' !== $claims['phoneNumberVerified']) {
            $claims['phoneNumberVerified'] = (bool) $claims['phoneNumberVerified'];
        }

        return new OidcUser(...$claims);
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
