<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Core;

final class AuthenticationEvents
{
    /**
     * The AUTHENTICATION_SUCCESS event occurs after a user is authenticated
     * by one provider.
     *
     * @Event("Symphony\Component\Security\Core\Event\AuthenticationEvent")
     */
    const AUTHENTICATION_SUCCESS = 'security.authentication.success';

    /**
     * The AUTHENTICATION_FAILURE event occurs after a user cannot be
     * authenticated by any of the providers.
     *
     * @Event("Symphony\Component\Security\Core\Event\AuthenticationFailureEvent")
     */
    const AUTHENTICATION_FAILURE = 'security.authentication.failure';
}
