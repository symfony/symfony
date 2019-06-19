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

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * AuthenticationException is the base class for all authentication exceptions.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Alexander <iam.asm89@gmail.com>
 */
class AuthenticationException extends \RuntimeException implements \Serializable
{
    private $token;

    /**
     * Get the token.
     *
     * @return TokenInterface|null
     */
    public function getToken()
    {
        return $this->token;
    }

    public function setToken(TokenInterface $token)
    {
        $this->token = $token;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        $serialized = [
            $this->token,
            $this->code,
            $this->message,
            $this->file,
            $this->line,
        ];

        return $this->doSerialize($serialized, \func_num_args() ? func_get_arg(0) : null);
    }

    /**
     * @internal
     */
    protected function doSerialize($serialized, $isCalledFromOverridingMethod)
    {
        if (null === $isCalledFromOverridingMethod) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 3);
            $isCalledFromOverridingMethod = isset($trace[2]['function'], $trace[2]['object']) && 'serialize' === $trace[2]['function'] && $this === $trace[2]['object'];
        }

        return $isCalledFromOverridingMethod ? $serialized : serialize($serialized);
    }

    public function unserialize($str)
    {
        list(
            $this->token,
            $this->code,
            $this->message,
            $this->file,
            $this->line
        ) = \is_array($str) ? $str : unserialize($str);
    }

    /**
     * Message key to be used by the translation component.
     *
     * @return string
     */
    public function getMessageKey()
    {
        return 'An authentication exception occurred.';
    }

    /**
     * Message data to be used by the translation component.
     *
     * @return array
     */
    public function getMessageData()
    {
        return [];
    }
}
