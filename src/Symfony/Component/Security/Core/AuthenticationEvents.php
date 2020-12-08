<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core;

use Symfony\Component\Security\Core\Event\AuthenticationFailureEvent;
use Symfony\Component\Security\Core\Event\AuthenticationSuccessEvent;

final class AuthenticationEvents
{
    /**
     * The AUTHENTICATION_SUCCESS event occurs after a user is authenticated
     * by one provider.
     *
     * @Event("Symfony\Component\Security\Core\Event\AuthenticationSuccessEvent")
     */
    public const AUTHENTICATION_SUCCESS = 'security.authentication.success';

    /**
     * The AUTHENTICATION_FAILURE event occurs after a user cannot be
     * authenticated by any of the providers.
     *
     * @Event("Symfony\Component\Security\Core\Event\AuthenticationFailureEvent")
     */
    public const AUTHENTICATION_FAILURE = 'security.authentication.failure';

    /**
     * Event aliases.
     *
     * These aliases can be consumed by RegisterListenersPass.
     */
    public const ALIASES = [
        AuthenticationSuccessEvent::class => self::AUTHENTICATION_SUCCESS,
        AuthenticationFailureEvent::class => self::AUTHENTICATION_FAILURE,
    ];
}
