<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Exception;

/**
 * This exception is thrown when the csrf token is invalid.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 * @author Alexander <iam.asm89@gmail.com>
 *
 * @since v2.0.0
 */
class InvalidCsrfTokenException extends AuthenticationException
{
    /**
     * {@inheritDoc}
     *
     * @since v2.2.0
     */
    public function getMessageKey()
    {
        return 'Invalid CSRF token.';
    }
}
