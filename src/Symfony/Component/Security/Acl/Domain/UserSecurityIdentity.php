<?php

namespace Symfony\Component\Security\Acl\Domain;

use Symfony\Component\Security\Acl\Model\SecurityIdentityInterface;
use Symfony\Component\Security\Authentication\Token\TokenInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * A SecurityIdentity implementation used for actual users
 *
 * FIXME: We need to also store the user provider id since the
 *        username might not be unique across all available user
 *        providers.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class UserSecurityIdentity implements SecurityIdentityInterface
{
    protected $username;

    /**
     * Constructor
     *
     * @param mixed $username the username representation, or a TokenInterface
     *              implementation
     * @return void
     */
    public function __construct($username)
    {
        if ($username instanceof TokenInterface) {
            $username = (string) $username;
        }

        if (0 === strlen($username)) {
            throw new \InvalidArgumentException('$username must not be empty.');
        }

        $this->username = $username;
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
     * {@inheritDoc}
     */
    public function equals(SecurityIdentityInterface $sid)
    {
        if (!$sid instanceof UserSecurityIdentity) {
            return false;
        }

        return $this->username === $sid->getUsername();
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
        return sprintf('UserSecurityIdentity(%s)', $this->username);
    }
}