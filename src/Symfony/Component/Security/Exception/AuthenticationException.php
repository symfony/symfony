<?php

namespace Symfony\Component\Security\Exception;

use Symfony\Component\Security\Authentication\Token\TokenInterface;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * AuthenticationException is the base class for all authentication exceptions.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class AuthenticationException extends \RuntimeException
{
    protected $token;

    public function __construct($message, TokenInterface $token = null, $code = 0, \Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->token = $token;
    }

    public function getToken()
    {
        return $this->token;
    }

    public function setToken(TokenInterface $token)
    {
        $this->token = $token;
    }
}
