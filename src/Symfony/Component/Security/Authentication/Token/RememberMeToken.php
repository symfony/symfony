<?php

namespace Symfony\Component\Security\Authentication\Token;

use Symfony\Component\Security\Authentication\RememberMe\PersistentTokenInterface;
use Symfony\Component\Security\User\AccountInterface;

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
    public function __construct(AccountInterface $user, $key) {
        parent::__construct($user->getRoles());

        if (0 === strlen($key)) {
            throw new \InvalidArgumentException('$key cannot be empty.');
        }

        $this->user = $user;
        $this->key = $key;
        $this->setAuthenticated(true);
    }

    public function getKey()
    {
        return $this->key;
    }

    public function setPersistentToken(PersistentTokenInterface $persistentToken)
    {
        $this->persistentToken = $persistentToken;
    }

    public function getPersistentToken()
    {
        return $this->persistentToken;
    }
}
