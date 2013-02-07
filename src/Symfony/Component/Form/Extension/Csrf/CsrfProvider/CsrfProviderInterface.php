<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Csrf\CsrfProvider;

/**
 * Marks classes able to provide CSRF protection
 *
 * You can generate a CSRF token by using the method generateCsrfToken(). To
 * this method you should pass a value that is unique to the page that should
 * be secured against CSRF attacks. This value doesn't necessarily have to be
 * secret. Implementations of this interface are responsible for adding more
 * secret information.
 *
 * If you want to secure a form submission against CSRF attacks, you could
 * supply an "intention" string. This way you make sure that the form can only
 * be bound to pages that are designed to handle the form, that is, that use
 * the same intention string to validate the CSRF token with isCsrfTokenValid().
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface CsrfProviderInterface
{
    /**
     * Generates a CSRF token for a page of your application.
     *
     * @param string $intention Some value that identifies the action intention
     *                          (i.e. "authenticate"). Doesn't have to be a secret value.
     */
    public function generateCsrfToken($intention);

    /**
     * Validates a CSRF token.
     *
     * @param string $intention The intention used when generating the CSRF token
     * @param string $token     The token supplied by the browser
     *
     * @return Boolean Whether the token supplied by the browser is correct
     */
    public function isCsrfTokenValid($intention, $token);
}
