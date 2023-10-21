<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Test;

use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A very limited token that is used to login in tests using the KernelBrowser.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
class TestBrowserToken extends AbstractToken
{
    private string $firewallName;

    public function __construct(array $roles = [], UserInterface $user = null, string $firewallName = 'main')
    {
        parent::__construct($roles);

        if (null !== $user) {
            $this->setUser($user);
        }

        $this->firewallName = $firewallName;
    }

    public function getFirewallName(): string
    {
        return $this->firewallName;
    }

    public function getCredentials(): mixed
    {
        return null;
    }

    public function __serialize(): array
    {
        return [$this->firewallName, parent::__serialize()];
    }

    public function __unserialize(array $data): void
    {
        [$this->firewallName, $parentData] = $data;

        parent::__unserialize($parentData);
    }
}
