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

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * AccountStatusException is the base class for authentication exceptions
 * caused by the user account status.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Alexander <iam.asm89@gmail.com>
 *
 * @since v2.0.0
 */
abstract class AccountStatusException extends AuthenticationException
{
    private $user;

    /**
     * Get the user.
     *
     * @return UserInterface
     *
     * @since v2.2.0
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set the user.
     *
     * @param UserInterface $user
     *
     * @since v2.2.0
     */
    public function setUser(UserInterface $user)
    {
        $this->user = $user;
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.2.0
     */
    public function serialize()
    {
        return serialize(array(
            $this->user,
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
        list($this->user, $parentData) = unserialize($str);

        parent::unserialize($parentData);
    }
}
