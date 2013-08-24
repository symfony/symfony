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
 *
 * @since v2.0.0
 */
class UsernameNotFoundException extends AuthenticationException
{
    private $username;

    /**
     * {@inheritDoc}
     *
     * @since v2.2.0
     */
    public function getMessageKey()
    {
        return 'Username could not be found.';
    }

    /**
     * Get the username.
     *
     * @return string
     *
     * @since v2.2.0
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * Set the username.
     *
     * @param string $username
     *
     * @since v2.2.0
     */
    public function setUsername($username)
    {
        $this->username = $username;
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.2.0
     */
    public function serialize()
    {
        return serialize(array(
            $this->username,
            parent::serialize(),
        ));
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.2.0
     */
    public function unserialize($str)
    {
        list($this->username, $parentData) = unserialize($str);

        parent::unserialize($parentData);
    }
}
