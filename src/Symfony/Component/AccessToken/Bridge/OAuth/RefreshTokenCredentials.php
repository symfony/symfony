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
 * OAuth2 "refresh_token" grant type.
 *
 * @see https://www.oauth.com/oauth2-servers/access-tokens/refreshing-access-tokens/
 *
 * @author Pierre Rineau <pierre.rineau@processus.org>
 */
class RefreshTokenCredentials extends AbstractOAuthCredentials
{
    use WithScopeTrait;

    /**
     * @param string $refreshToken              Token previously issued by the authorization endpoint.
     * @param null|string $clientId             Client ID that was previously issued to the authorization endpoint.
     * @param null|string $clientSecret         Client secret that was previously issued to the authorization endpoint.
     * @param null|string $tenant               Tenant name or identifier.
     * @param null|string|array<string> $scope  Scopes or subset of scopes that were previously issued to the authorization endpoint.
     * @param null|string $endpoint             Authorization endpoint URL, for generic usage you must provide one.
     */
    public function __construct(
        #[\SensitiveParameter] private readonly string $refreshToken,
        #[\SensitiveParameter] private readonly ?string $clientId = null,
        #[\SensitiveParameter] private readonly ?string $clientSecret = null,
        #[\SensitiveParameter] ?string $tenant = null,
        null|string|array $scope = null,
        ?string $endpoint = null,
    ) {
        parent::__construct(
            tenant: $tenant,
            endpoint: $endpoint,
        );

        $this->scope = \is_string($scope) ? \array_filter(\explode(' ', $scope)) : $scope;
    }

    #[\Override]
    public function getGrantType(): string
    {
        return 'refresh_token';
    }

    public function getRefreshToken(): string
    {
        return $this->refreshToken;
    }

    public function getClientId(): ?string
    {
        return $this->clientId;
    }

    public function getClientSecret(): ?string
    {
        return $this->clientSecret;
    }

    #[\Override]
    protected function computeId(): string
    {
        return \md5($this->getEndpoint() . $this->clientId . $this->getTenant() . $this->getScopeAsString());
    }
}
