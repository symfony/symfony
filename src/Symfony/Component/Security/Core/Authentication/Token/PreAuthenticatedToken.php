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

/**
 * PreAuthenticatedToken implements a pre-authenticated token.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @since v2.0.0
 */
class PreAuthenticatedToken extends AbstractToken
{
    private $credentials;
    private $providerKey;

    /**
     * Constructor.
     *
     * @since v2.0.0
     */
    public function __construct($user, $credentials, $providerKey, array $roles = array())
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
     * @since v2.0.0
     */
    public function getProviderKey()
    {
        return $this->providerKey;
    }

    /**
     * @since v2.0.0
     */
    public function getCredentials()
    {
        return $this->credentials;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.0.0
     */
    public function eraseCredentials()
    {
        parent::eraseCredentials();

        $this->credentials = null;
    }

    /**
     * @since v2.0.0
     */
    public function serialize()
    {
        return serialize(array($this->credentials, $this->providerKey, parent::serialize()));
    }

    /**
     * @since v2.0.0
     */
    public function unserialize($str)
    {
        list($this->credentials, $this->providerKey, $parentStr) = unserialize($str);
        parent::unserialize($parentStr);
    }
}
