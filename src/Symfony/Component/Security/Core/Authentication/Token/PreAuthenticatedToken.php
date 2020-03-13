<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authentication\Token;

use Symfony\Component\Security\Core\Role\Role;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * PreAuthenticatedToken implements a pre-authenticated token.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class PreAuthenticatedToken extends AbstractToken
{
    private $credentials;
    private $providerKey;

    /**
     * @param string|\Stringable|UserInterface $user
     * @param mixed                            $credentials
     * @param string                           $providerKey
     * @param (Role|string)[]                  $roles
     */
    public function __construct($user, $credentials, $providerKey, array $roles = [])
    {
        parent::__construct($roles);

        if (empty($providerKey)) {
            throw new \InvalidArgumentException('$providerKey must not be empty.');
        }

        $this->setUser($user);
        $this->credentials = $credentials;
        $this->providerKey = $providerKey;

        if ($roles) {
            $this->setAuthenticated(true);
        }
    }

    /**
     * Returns the provider key.
     *
     * @return string The provider key
     */
    public function getProviderKey()
    {
        return $this->providerKey;
    }

    /**
     * {@inheritdoc}
     */
    public function getCredentials()
    {
        return $this->credentials;
    }

    /**
     * {@inheritdoc}
     */
    public function eraseCredentials()
    {
        parent::eraseCredentials();

        $this->credentials = null;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        $serialized = [$this->credentials, $this->providerKey, parent::serialize(true)];

        return $this->doSerialize($serialized, \func_num_args() ? func_get_arg(0) : null);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($str)
    {
        list($this->credentials, $this->providerKey, $parentStr) = \is_array($str) ? $str : unserialize($str);
        parent::unserialize($parentStr);
    }
}
