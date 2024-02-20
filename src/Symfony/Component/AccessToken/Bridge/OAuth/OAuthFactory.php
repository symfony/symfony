<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare (strict_types=1);

namespace Symfony\Component\AccessToken\Bridge\OAuth;

use Symfony\Component\AccessToken\CredentialsInterface;
use Symfony\Component\AccessToken\Credentials\Dsn;
use Symfony\Component\AccessToken\Credentials\FactoryInterface;
use Symfony\Component\AccessToken\Exception\InvalidArgumentException;

/**
 * OAuth2 credentials factory.
 *
 * @author Pierre Rineau <pierre.rineau@processus.org>
 */
class OAuthFactory implements FactoryInterface
{
    #[\Override]
    public function createCredentials(Dsn $dsn): CredentialsInterface
    {
        $grantType = $dsn->getOption('grant_type') ?? 'client_credentials';

        if ('client_credentials' === $grantType) {
            if (!$clientId = $dsn->getOption('client_id')) {
                $clientId = $dsn->getUser() ?? throw new InvalidArgumentException('"user" or "client_id" parameter is missing for OAuth "client_credentials" grant type access token URL');
            }
            if (!$clientSecret = $dsn->getOption('client_secret')) {
                $clientSecret = $dsn->getPassword() ?? throw new InvalidArgumentException('"password" or "client_secret" parameter is missing for OAuth "client_credentials" grant type access token URL');
            }

            return new ClientCredentials(
                clientId: $clientId,
                clientSecret: $clientSecret,
                tenant: $dsn->getOption('tenant'),
                scope: $dsn->getOption('scope'),
                endpoint: $dsn->toEndpointUrl(['grant_type', 'client_id', 'client_secret', 'tenant', 'scope']),
            );
        }

        if ('refresh_token' === $grantType) {
            if (!$refreshToken = $dsn->getOption('refresh_token')) {
                throw new InvalidArgumentException('"refresh_token" parameter is missing for OAuth "refresh_token" grant type access token URL');
            }
            if (!$clientId = $dsn->getOption('client_id')) {
                $clientId = $dsn->getUser();
            }
            if (!$clientSecret = $dsn->getOption('client_secret')) {
                $clientSecret = $dsn->getPassword();
            }

            return new RefreshTokenCredentials(
                refreshToken: $refreshToken,
                clientId: $clientId,
                clientSecret: $clientSecret,
                tenant: $dsn->getOption('tenant'),
                scope: $dsn->getOption('scope'),
                endpoint: $dsn->toEndpointUrl(['grant_type', 'refresh_token', 'client_id', 'client_secret', 'tenant', 'scope']),
            );
        }

        throw new InvalidArgumentException(\sprintf('"%s" grant type is unsupported for OAuth access token URL', $grantType));
    }
}
