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
 * AccessDeniedException is thrown when the account has not the required role.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @since v2.0.0
 */
class AccessDeniedException extends \RuntimeException
{
    /**
     * @since v2.0.0
     */
    public function __construct($message = 'Access Denied', \Exception $previous = null)
    {
        parent::__construct($message, 403, $previous);
    }
}
