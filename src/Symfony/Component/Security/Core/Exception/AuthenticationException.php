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
 *
 * @since v2.0.0
 */
class AuthenticationException extends \RuntimeException implements \Serializable
{
    private $token;

    /**
     * Get the token.
     *
     * @return TokenInterface
     *
     * @since v2.2.0
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * Set the token.
     *
     * @param TokenInterface $token
     *
     * @since v2.2.0
     */
    public function setToken(TokenInterface $token)
    {
        $this->token = $token;
    }

    /**
     * @since v2.0.0
     */
    public function serialize()
    {
        return serialize(array(
            $this->token,
            $this->code,
            $this->message,
            $this->file,
            $this->line,
        ));
    }

    /**
     * @since v2.0.0
     */
    public function unserialize($str)
    {
        list(
            $this->token,
            $this->code,
            $this->message,
            $this->file,
            $this->line
        ) = unserialize($str);
    }

    /**
     * Message key to be used by the translation component.
     *
     * @return string
     *
     * @since v2.2.0
     */
    public function getMessageKey()
    {
        return 'An authentication exception occurred.';
    }

    /**
     * Message data to be used by the translation component.
     *
     * @return array
     *
     * @since v2.2.0
     */
    public function getMessageData()
    {
        return array();
    }
}
