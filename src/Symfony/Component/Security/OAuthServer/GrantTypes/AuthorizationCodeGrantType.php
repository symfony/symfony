<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\OAuthServer\GrantTypes;

use Symfony\Component\Security\OAuthServer\Request\AbstractRequest;
use Symfony\Component\Security\OAuthServer\Request\AuthorizationRequest;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class AuthorizationCodeGrantType extends AbstractGrantType
{
    protected const RESPONSE_TYPE = 'code';
    private const ACCESS_TOKEN_REQUEST_TYPE = 'authorization_code';

    private $responsePayload = [];

    public function canHandleRequest(AuthorizationRequest $authorizationRequest): bool
    {
        return self::RESPONSE_TYPE === $authorizationRequest->getType($authorizationRequest);
    }

    public function canHandleAccessTokenRequest(AbstractRequest $request): bool
    {
        return self::ACCESS_TOKEN_REQUEST_TYPE === $request->getValue('grant_type');
    }

    public function handle(AuthorizationRequest $request)
    {
        return $this->returnResponsePayload();
    }

    public function handleAuthorizationRequest(AuthorizationRequest $request)
    {
        // TODO: Implement handleAuthorizationRequest() method.
    }

    public function handleAccessTokenRequest(AuthorizationRequest $request)
    {
        // TODO: Implement handleAccessTokenRequest() method.
    }

    public function handleRefreshTokenRequest(AuthorizationRequest $request)
    {
        // TODO: Implement handleRefreshTokenRequest() method.
    }

    public function returnResponsePayload(): array
    {
        return $this->responsePayload;
    }
}
