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

use Symfony\Component\Security\Core\Exception\InvalidArgumentException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Authentication Token for "Remember-Me".
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class RememberMeToken extends AbstractToken
{
    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(
        UserInterface $user,
        private string $firewallName,
    ) {
        parent::__construct($user->getRoles());

        if (!$firewallName) {
            throw new InvalidArgumentException('$firewallName must not be empty.');
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
