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
 * PreAuthenticatedToken implements a pre-authenticated token.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class PreAuthenticatedToken extends AbstractToken
{
    /**
     * @param string[] $roles
     */
    public function __construct(
        UserInterface $user,
        private string $firewallName,
        array $roles = [],
    ) {
        parent::__construct($roles);

        if ('' === $firewallName) {
            throw new \InvalidArgumentException('$firewallName must not be empty.');
        }

        $this->setUser($user);
    }

    public function getFirewallName(): string
    {
        return $this->firewallName;
    }

    public function __serialize(): array
    {
        return [null, $this->firewallName, parent::__serialize()];
    }

    public function __unserialize(array $data): void
    {
        [, $this->firewallName, $parentData] = $data;
        $parentData = \is_array($parentData) ? $parentData : unserialize($parentData);
        parent::__unserialize($parentData);
    }
}
