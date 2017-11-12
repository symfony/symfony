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
     */
    public function getToken(): TokenInterface
    {
        return $this->token;
    }

    public function setToken(TokenInterface $token): void
    {
        $this->token = $token;
    }

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

    public function unserialize($str): void
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
     */
    public function getMessageKey(): string
    {
        return 'An authentication exception occurred.';
    }

    /**
     * Message data to be used by the translation component.
     */
    public function getMessageData(): array
    {
        return array();
    }
}
