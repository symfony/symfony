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

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Token representing a user who temporarily impersonates another one.
 *
 * @author Christian Flothmann <christian.flothmann@sensiolabs.de>
 */
class SwitchUserToken extends UsernamePasswordToken
{
    private $originalToken;

    /**
     * @param string|\Stringable|UserInterface $user
     * @param mixed                            $credentials
     * @param string[]                         $roles
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
