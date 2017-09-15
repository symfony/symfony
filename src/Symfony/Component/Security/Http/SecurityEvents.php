<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http;

final class SecurityEvents
{
    /**
     * The INTERACTIVE_LOGIN event occurs after a user has actively logged
     * into your website. It is important to distinguish this action from
     * non-interactive authentication methods, such as:
     *   - authentication based on your session.
     *   - authentication using a HTTP basic or HTTP digest header.
     *
     * @Event("Symfony\Component\Security\Http\Event\InteractiveLoginEvent")
     *
     * @var string
     */
    const INTERACTIVE_LOGIN = 'security.interactive_login';

    /**
     * The INTERACTIVE_LOGIN_FAILURE event occurs after a user fails to log in
     * interactively for authentication based on http, cookies or X509.
     *
     * @Event("Symfony\Component\Security\Http\Event\InteractiveLoginFailureEvent")
     *
     * @var string
     */
    const INTERACTIVE_LOGIN_FAILURE = 'security.interactive_login_failure';

    /**
     * The SWITCH_USER event occurs before switch to another user and
     * before exit from an already switched user.
     *
     * @Event("Symfony\Component\Security\Http\Event\SwitchUserEvent")
     *
     * @var string
     */
    const SWITCH_USER = 'security.switch_user';
}
