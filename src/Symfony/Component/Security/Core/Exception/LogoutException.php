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
 * LogoutException is thrown when the account cannot be logged out.
 *
 * @author Jeremy Mikola <jmikola@gmail.com>
 *
 * @since v2.1.0
 */
class LogoutException extends \RuntimeException
{
    /**
     * @since v2.1.0
     */
    public function __construct($message = 'Logout Exception', \Exception $previous = null)
    {
        parent::__construct($message, 403, $previous);
    }
}
