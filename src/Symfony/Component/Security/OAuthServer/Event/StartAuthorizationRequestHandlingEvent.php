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
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class StartAuthorizationRequestHandlingEvent extends Event
{
    private $authorizationRequest;

    public function __construct(AuthorizationRequest $authorizationRequest)
    {
        $this->authorizationRequest = $authorizationRequest;
    }

    public function getAuthorizationRequest(): AuthorizationRequest
    {
        return $this->authorizationRequest;
    }
}
