<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation;

use Symfony\Component\HttpFoundation\Exception\ExpiredSignedUriException;
use Symfony\Component\HttpFoundation\Exception\SignedUriException;
use Symfony\Component\HttpFoundation\Exception\UnsignedUriException;
use Symfony\Component\HttpFoundation\Exception\UnverifiedSignedUriException;

/**
 * @author Santiago San Martin <sanmartindev@gmail.com>
 */
interface UriSignerInterface
{
    /**
     * Signs a URI.
     *
     * The given URI is signed by adding the query string parameter
     * which value depends on the URI and the secret.
     *
     * @param \DateTimeInterface|\DateInterval|int|null $expiration The expiration for the given URI.
     *                                                              If $expiration is a \DateTimeInterface, it's expected to be the exact date + time.
     *                                                              If $expiration is a \DateInterval, the interval is added to "now" to get the date + time.
     *                                                              If $expiration is an int, it's expected to be a timestamp in seconds of the exact date + time.
     *                                                              If $expiration is null, no expiration.
     *
     * The expiration is added as a query string parameter.
     */
    public function sign(string $uri/* , \DateTimeInterface|\DateInterval|int|null $expiration = null */): string;

    /**
     * Checks that a URI contains the correct hash.
     * Also checks if the URI has not expired (If you used expiration during signing).
     */
    public function check(string $uri): bool;

    /**
     * Checks that a Request contains the correct hash.
     * Also checks if the URI has not expired (if you used expiration during signing).
     */
    public function checkRequest(Request $request): bool;

    /**
     * Verify a Request or string URI.
     *
     * @throws UnsignedUriException         If the URI is not signed
     * @throws UnverifiedSignedUriException If the signature is invalid
     * @throws ExpiredSignedUriException    If the URI has expired
     * @throws SignedUriException
     */
    public function verify(Request|string $uri): void;
}
