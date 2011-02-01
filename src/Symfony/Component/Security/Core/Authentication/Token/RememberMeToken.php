<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authentication\Token;

use Symfony\Component\Security\Core\Authentication\RememberMe\PersistentTokenInterface;
use Symfony\Component\Security\Core\User\AccountInterface;

/**
 * Base class for "Remember Me" tokens
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class RememberMeToken extends Token
{
    protected $key;

    /**
     * The persistent token which resulted in this authentication token.
     *
     * @var PersistentTokenInterface
     */
    protected $persistentToken;

    /**
     * Constructor.
     *
     * @param string $username
     * @param string $key
     */
    public function __construct(AccountInterface $user, $providerKey, $key) {
        parent::__construct($user->getRoles());

        if (empty($key)) {
            throw new \InvalidArgumentException('$key must not be empty.');
        }
        if (empty($providerKey)) {
            throw new \InvalidArgumentException('$providerKey must not be empty.');
        }

        $this->setUser($user);
        $this->providerKey = $providerKey;
        $this->key = $key;
        $this->setAuthenticated(true);
    }

    public function getKey()
    {
        return $this->key;
    }

    public function getPersistentToken()
    {
        return $this->persistentToken;
    }

    public function setPersistentToken(PersistentTokenInterface $persistentToken)
    {
        $this->persistentToken = $persistentToken;
    }
}