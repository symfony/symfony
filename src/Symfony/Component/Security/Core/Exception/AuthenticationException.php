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
 * AuthenticationException is the base class for all authentication exceptions.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Alexander <iam.asm89@gmail.com>
 */
class AuthenticationException extends \RuntimeException implements \Serializable
{
    private $extraInformation;

    public function getExtraInformation()
    {
        return $this->extraInformation;
    }

    public function setExtraInformation($extraInformation)
    {
        $this->extraInformation = $extraInformation;
    }

    public function serialize()
    {
        return serialize(array(
            $this->extraInformation,
            $this->code,
            $this->message,
            $this->file,
            $this->line,
        ));
    }

    public function unserialize($str)
    {
        list(
            $this->extraInformation,
            $this->code,
            $this->message,
            $this->file,
            $this->line
        ) = unserialize($str);
    }
}
