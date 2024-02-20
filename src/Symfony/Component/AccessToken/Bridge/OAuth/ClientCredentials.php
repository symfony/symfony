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

/**
 * OAuth2 "client_credentials" grant type.
 *
 * @see https://www.oauth.com/oauth2-servers/access-tokens/client-credentials/
 *
 * @author Pierre Rineau <pierre.rineau@processus.org>
 */
class ClientCredentials extends AbstractOAuthCredentials
{
    use WithScopeTrait;

    /**
     * @param string $clientId                  Client ID.
     * @param string $clientSecret              Client secret.
     * @param null|string $tenant               Tenant name or identifier.
     * @param null|string|array<string> $scope  Requested scopes, either as a whitespace separated list or an array of strings.
     * @param null|string $endpoint             Authorization endpoint URL, for generic usage you must provide one.
     */
    public function __construct(
        #[\SensitiveParameter] private readonly string $clientId,
        #[\SensitiveParameter] private readonly string $clientSecret,
        #[\SensitiveParameter] ?string $tenant = null,
        null|string|array $scope = null,
        ?string $endpoint = null,
    ) {
        parent::__construct(
            tenant: $tenant,
            endpoint: $endpoint,
        );

        $this->scope = is_string($scope) ? array_filter(explode(' ', $scope)) : $scope;
    }

    #[\Override]
    public function getGrantType(): string
    {
        return 'client_credentials';
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function getClientSecret(): string
    {
        return $this->clientSecret;
    }

    /**
     * Convert to refresh token.
     *
     * @param string $refreshToken  Token previously issued by the authorization endpoint.
     */
    public function createRefreshToken(string $refreshToken): RefreshTokenCredentials
    {
        return new RefreshTokenCredentials(
            refreshToken: $refreshToken,
            clientId: $this->clientId,
            clientSecret: $this->clientSecret,
            tenant: $this->getTenant(),
            scope: $this->scope,
            endpoint: $this->getEndpoint(),
        );
    }

    #[\Override]
    protected function computeId(): string
    {
        return md5($this->getEndpoint() . $this->clientId . $this->getTenant() . $this->getScopeAsString());
    }
}
