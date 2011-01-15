<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Acl\Domain;

use Symfony\Component\Security\User\AccountInterface;
use Symfony\Component\Security\Acl\Model\SecurityIdentityInterface;

/**
 * A SecurityIdentity implementation used for actual users
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class UserSecurityIdentity implements SecurityIdentityInterface
{
    protected $username;
    protected $class;

    /**
     * Constructor
     *
     * @param string $username the username representation
     * @param string $class the user's fully qualified class name
     */
    public function __construct($username, $class)
    {
        if (empty($username)) {
            throw new \InvalidArgumentException('$username must not be empty.');
        }
        if (empty($class)) {
            throw new \InvalidArgumentException('$class must not be empty.');
        }

        $this->username = $username;
        $this->class = $class;
    }

    /**
     * Creates a user security identity from an AccountInterface
     *
     * @param AccountInterface $user
     * @return UserSecurityIdentity
     */
    public static function fromAccount(AccountInterface $user)
    {
        return new self((string) $user, get_class($user));
    }

    /**
     * Returns the username
     *
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * Returns the user's class name
     *
     * @return string
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * {@inheritDoc}
     */
    public function equals(SecurityIdentityInterface $sid)
    {
        if (!$sid instanceof UserSecurityIdentity) {
            return false;
        }

        return $this->username === $sid->getUsername()
               && $this->class === $sid->getClass();
    }

    /**
     * A textual representation of this security identity.
     *
     * This is not used for equality comparison, but only for debugging.
     *
     * @return string
     */
    public function __toString()
    {
        return sprintf('UserSecurityIdentity(%s, %s)', $this->username, $this->class);
    }
}