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
 * AuthenticationException is the base class for all authentication exceptions.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class AuthenticationException extends \RuntimeException
{
    protected $extraInformation;

    public function __construct($message, $extraInformation = null, $code = 0, \Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->extraInformation = $extraInformation;
    }

    public function getExtraInformation()
    {
        return $this->extraInformation;
    }

    public function setExtraInformation($extraInformation)
    {
        $this->extraInformation = $extraInformation;
    }
}
