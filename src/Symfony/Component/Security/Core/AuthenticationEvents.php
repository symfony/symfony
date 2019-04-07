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

final class AuthenticationEvents
{
    /**
     * The AUTHENTICATION_SUCCESS_SENSITIVE event occurs after a user is
     * authenticated by one provider. It is dispatched immediately *prior* to
     * the companion AUTHENTICATION_SUCCESS event.
     *
     * This event *does* contain user credentials and other sensitive data. This
     * enables rehashing and other credentials-aware actions. Listeners and
     * subscribers of this event carry the added responsibility of passing
     * around sensitive data and usage should be limited to cases where this
     * extra information is explicitly utilized; otherwise, use the
     * AUTHENTICATION_SUCCESS event instead.
     *
     * @Event("Symfony\Component\Security\Core\Event\AuthenticationSensitiveEvent")
     */
    const AUTHENTICATION_SUCCESS_SENSITIVE = 'security.authentication.success_sensitive';

    /**
     * The AUTHENTICATION_SUCCESS event occurs after a user is authenticated
     * by one provider. It is dispatched immediately *after* the companion
     * AUTHENTICATION_SUCCESS_SENSITIVE event.
     *
     * This event does *not* contain user credentials and other sensitive data
     * by default. Listeners and subscribers of this event are shielded from
     * the added responsibility of passing around sensitive data and this event
     * should be used unless such extra information is required; use the
     * AUTHENTICATION_SUCCESS_SENSITIVE event instead if this is the case.
     *
     * @Event("Symfony\Component\Security\Core\Event\AuthenticationSuccessEvent")
     */
    const AUTHENTICATION_SUCCESS = 'security.authentication.success';

    /**
     * The AUTHENTICATION_FAILURE event occurs after a user cannot be
     * authenticated by any of the providers.
     *
     * @Event("Symfony\Component\Security\Core\Event\AuthenticationFailureEvent")
     */
    const AUTHENTICATION_FAILURE = 'security.authentication.failure';
}
