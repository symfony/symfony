<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\CsrfProvider;

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
 * use the class name of the form as page ID. This way you make sure that the
 * form can only be submitted to pages that are designed to handle the form,
 * that is, that use the same class name to validate the CSRF token with
 * isCsrfTokenValid().
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
interface CsrfProviderInterface
{
    /**
     * Generates a CSRF token for a page of your application
     *
     * @param string $pageId  Some value that identifies the page (for example,
     *                        the class name of the form). Doesn't have to be
     *                        a secret value.
     */
    public function generateCsrfToken($pageId);

    /**
     * Validates a CSRF token
     *
     * @param  string $pageId  The page ID used when generating the CSRF token
     * @param  string $token   The token supplied by the browser
     * @return boolean         Whether the token supplied by the browser is
     *                         correct
     */
    public function isCsrfTokenValid($pageId, $token);
}