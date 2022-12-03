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
    private TokenInterface $originalToken;
    private ?string $originatedFromUri = null;

    /**
     * @param $user              The username (like a nickname, email address, etc.), or a UserInterface instance or an object implementing a __toString method
     * @param $originatedFromUri The URI where was the user at the switch
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(UserInterface $user, string $firewallName, array $roles, TokenInterface $originalToken, string $originatedFromUri = null)
    {
        parent::__construct($user, $firewallName, $roles);

        $this->originalToken = $originalToken;
        $this->originatedFromUri = $originatedFromUri;
    }

    public function getOriginalToken(): TokenInterface
    {
        return $this->originalToken;
    }

    public function getOriginatedFromUri(): ?string
    {
        return $this->originatedFromUri;
    }

    public function __serialize(): array
    {
        return [$this->originalToken, $this->originatedFromUri, parent::__serialize()];
    }

    public function __unserialize(array $data): void
    {
        if (3 > \count($data)) {
            // Support for tokens serialized with version 5.1 or lower of symfony/security-core.
            [$this->originalToken, $parentData] = $data;
        } else {
            [$this->originalToken, $this->originatedFromUri, $parentData] = $data;
        }
        $parentData = \is_array($parentData) ? $parentData : unserialize($parentData);
        parent::__unserialize($parentData);
    }
}
