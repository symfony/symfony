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
interface GrantTypeInterface
{
    /**
     * Allow to define if a request can be handled by the GrantType.
     *
     * The way that the handling is determined is up to the user.
     *
     * @param AuthorizationRequest $authorizationRequest the internal authorization request
     *
     * @return bool if the request can be handled
     */
    public function canHandleRequest(AuthorizationRequest $authorizationRequest): bool;

    public function canHandleAccessTokenRequest(AbstractRequest $request): bool;

    public function handleAuthorizationRequest(AuthorizationRequest $request);

    public function handleAccessTokenRequest(AuthorizationRequest $request);

    public function handleRefreshTokenRequest(AuthorizationRequest $request);

    public function returnResponsePayload(): array;
}
