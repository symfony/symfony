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

/**
 * List of request attributes used along the security flow.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
final class SecurityRequestAttributes
{
    public const ACCESS_DENIED_ERROR = '_security.403_error';
    public const AUTHENTICATION_ERROR = '_security.last_error';
    public const LAST_USERNAME = '_security.last_username';

    /**
     * Holds an UnsupportedReasons collector while an authenticator's supports() method runs, when the profiler is enabled.
     *
     * @see Authenticator\Debug\UnsupportedReasons
     */
    public const UNSUPPORTED_REASONS = '_security.unsupported_reasons';
}
