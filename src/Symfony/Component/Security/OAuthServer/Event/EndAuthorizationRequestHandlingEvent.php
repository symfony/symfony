<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\OAuthServer\Event;

use Symfony\Component\Security\OAuthServer\Request\AuthorizationRequest;
use Symfony\Component\Security\OAuthServer\Response\AuthorizationResponse;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * This event allows the user to modify the AuthorizationResponse before returning it,
 * by default, the AuthorizationRequest is returned as "read-only" as it should not be modified.
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class EndAuthorizationRequestHandlingEvent extends Event
{
    private $authorizationRequest;
    private $authorizationResponse;

    public function __construct(AuthorizationRequest $request, AuthorizationResponse $authorizationResponse)
    {
        $this->authorizationRequest = $request;
        $this->authorizationResponse = $authorizationResponse;
    }

    public function getAuthorizationRequest(): array
    {
        return $this->authorizationRequest->returnAsReadOnly();
    }

    public function getAuthorizationResponse(): AuthorizationResponse
    {
        return $this->authorizationResponse;
    }
}
