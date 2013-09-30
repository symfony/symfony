<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Csrf;

/**
 * Generates and validates CSRF tokens.
 *
 * You can generate a CSRF token by using the method {@link generateCsrfToken()}.
 * This method expects a unique token ID as argument. The token ID can later be
 * used to validate a token provided by the user.
 *
 * Token IDs do not necessarily have to be secret, but they should NEVER be
 * created from data provided by the client. A good practice is to hard-code the
 * token IDs for the various CSRF tokens used by your application.
 *
 * You should use the method {@link isCsrfTokenValid()} to check a CSRF token
 * submitted by the client. This method will return true if the CSRF token is
 * valid.
 *
 * @since  2.4
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface CsrfTokenGeneratorInterface
{
    /**
     * Generates a CSRF token with the given token ID.
     *
     * @param string $tokenId An ID that identifies the token
     *
     * @return string The generated CSRF token
     */
    public function generateCsrfToken($tokenId);

    /**
     * Validates a CSRF token.
     *
     * @param string $tokenId The token ID used when generating the token
     * @param string $token   The token supplied by the client
     *
     * @return Boolean Whether the token supplied by the client is correct
     */
    public function isCsrfTokenValid($tokenId, $token);
}
