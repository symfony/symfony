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
 * Token representing a user who temporarily impersonates another one.
 *
 * @author Christian Flothmann <christian.flothmann@sensiolabs.de>
 */
class SwitchUserToken extends UsernamePasswordToken
{
    private $originalToken;

    /**
     * @param string|object $user        The username (like a nickname, email address, etc.), or a UserInterface instance or an object implementing a __toString method
     * @param mixed         $credentials This usually is the password of the user
     * @param string        $providerKey The provider key
     * @param string[]      $roles       An array of roles
     *
     * @throws \InvalidArgumentException
     */
    public function __construct($user, $credentials, string $providerKey, array $roles = [], TokenInterface $originalToken)
    {
        parent::__construct($user, $credentials, $providerKey, $roles);

        $this->originalToken = $originalToken;
    }

    public function getOriginalToken(): TokenInterface
    {
        return $this->originalToken;
    }

    /**
     * {@inheritdoc}
     */
    public function __serialize(): array
    {
        return [$this->originalToken, parent::__serialize()];
    }

    /**
     * {@inheritdoc}
     */
    public function __unserialize(array $data): void
    {
        [$this->originalToken, $parentData] = $data;
        parent::__unserialize($parentData);
    }
}
