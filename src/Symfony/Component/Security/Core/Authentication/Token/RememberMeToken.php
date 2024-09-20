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
    private ?string $secret = null;

    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(
        UserInterface $user,
        private string $firewallName,
    ) {
        parent::__construct($user->getRoles());

        if (\func_num_args() > 2) {
            trigger_deprecation('symfony/security-core', '7.2', 'The "$secret" argument of "%s()" is deprecated.', __METHOD__);
            $this->secret = func_get_arg(2);
        }

        if (!$firewallName) {
            throw new InvalidArgumentException('$firewallName must not be empty.');
        }

        $this->setUser($user);
    }

    public function getFirewallName(): string
    {
        return $this->firewallName;
    }

    /**
     * @deprecated since Symfony 7.2
     */
    public function getSecret(): string
    {
        trigger_deprecation('symfony/security-core', '7.2', 'The "%s()" method is deprecated.', __METHOD__);

        return $this->secret ??= base64_encode(random_bytes(8));
    }

    public function __serialize(): array
    {
        // $this->firewallName should be kept at index 1 for compatibility with payloads generated before Symfony 8
        return [$this->secret, $this->firewallName, parent::__serialize()];
    }

    public function __unserialize(array $data): void
    {
        [$this->secret, $this->firewallName, $parentData] = $data;
        $parentData = \is_array($parentData) ? $parentData : unserialize($parentData);
        parent::__unserialize($parentData);
    }
}
