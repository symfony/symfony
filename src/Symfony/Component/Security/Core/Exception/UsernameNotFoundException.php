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
 * UsernameNotFoundException is thrown if a User cannot be found by its username.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Alexander <iam.asm89@gmail.com>
 */
class UsernameNotFoundException extends AuthenticationException
{
    private $username;

    /**
     * {@inheritdoc}
     */
    public function getMessageKey()
    {
        return 'Username could not be found.';
    }

    /**
     * Get the username.
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * Set the username.
     */
    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        return serialize(array(
            $this->username,
            parent::serialize(),
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($str): void
    {
        list($this->username, $parentData) = unserialize($str);

        parent::unserialize($parentData);
    }

    /**
     * {@inheritdoc}
     */
    public function getMessageData()
    {
        return array('{{ username }}' => $this->username);
    }
}
