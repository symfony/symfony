<?php

namespace Symfony\Component\Security\Exception;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * BadCredentialsException is thrown when the user credentials are invalid.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class BadCredentialsException extends AuthenticationException
{
    public function __construct($message, $code = 0, \Exception $previous = null)
    {
        parent::__construct($message, null, $code, $previous);
    }
}
