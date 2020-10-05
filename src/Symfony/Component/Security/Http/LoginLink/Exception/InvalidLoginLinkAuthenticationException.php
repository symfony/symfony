<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\LoginLink\Exception;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Thrown when a login link is invalid.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 * @experimental in 5.2
 */
class InvalidLoginLinkAuthenticationException extends AuthenticationException
{
    /**
     * {@inheritdoc}
     */
    public function getMessageKey()
    {
        return 'Invalid or expired login link.';
    }
}
