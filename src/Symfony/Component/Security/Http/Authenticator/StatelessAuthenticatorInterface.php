<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator;

/**
 * Marker interface for authenticators that do not require session persistence.
 *
 * When all authenticators supporting a request implement this interface,
 * the security system will automatically treat the request as stateless:
 * no token will be persisted to the session, and no session or profiler
 * debug cookies will be set on the response.
 *
 * @author Jonathan Bonjour <hi@gnutix.dev>
 */
interface StatelessAuthenticatorInterface extends AuthenticatorInterface
{
}
