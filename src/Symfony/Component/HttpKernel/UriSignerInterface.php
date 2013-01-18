<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel;

/**
 * Interface to be implemented by URI signers.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface UriSignerInterface
{
    /**
     * Signs a URI.
     *
     * The given URI is signed by adding a _hash query string parameter
     * which value depends on the URI and the secret.
     *
     * @param string $uri A URI to sign
     *
     * @return string The signed URI
     */
    public function sign($uri);

    /**
     * Checks that a URI contains the correct hash.
     *
     * The _hash query string parameter must be the last one
     * (as it is generated that way by the sign() method, it should
     * never be a problem).
     *
     * @param string $uri A signed URI
     *
     * @return Boolean Whether the signature is verified
     */
    public function check($uri);
}
