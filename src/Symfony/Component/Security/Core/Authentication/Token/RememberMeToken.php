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
 * Authentication Token for "Remember-Me".
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class RememberMeToken extends AbstractToken
{
    private $secret;
    private $firewallName;

    /**
     * @param string $secret A secret used to make sure the token is created by the app and not by a malicious client
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(UserInterface $user, string $firewallName, string $secret)
    {
        parent::__construct($user->getRoles());

        if (empty($secret)) {
            throw new \InvalidArgumentException('$secret must not be empty.');
        }

        if ('' === $firewallName) {
            throw new \InvalidArgumentException('$firewallName must not be empty.');
        }

        $this->firewallName = $firewallName;
        $this->secret = $secret;

        $this->setUser($user);
        parent::setAuthenticated(true, false);
    }

    /**
     * {@inheritdoc}
     */
    public function setAuthenticated(bool $authenticated)
    {
        if ($authenticated) {
            throw new \LogicException('You cannot set this token to authenticated after creation.');
        }

        parent::setAuthenticated(false, false);
    }

    /**
     * Returns the provider secret.
     *
     * @return string The provider secret
     *
     * @deprecated since Symfony 5.2, use getFirewallName() instead
     */
    public function getProviderKey()
    {
        if (1 !== \func_num_args() || true !== func_get_arg(0)) {
            trigger_deprecation('symfony/security-core', '5.2', 'Method "%s()" is deprecated, use "getFirewallName()" instead.', __METHOD__);
        }

        return $this->firewallName;
    }

    public function getFirewallName(): string
    {
        return $this->getProviderKey(true);
    }

    /**
     * @return string
     */
    public function getSecret()
    {
        return $this->secret;
    }

    /**
     * {@inheritdoc}
     */
    public function getCredentials()
    {
        trigger_deprecation('symfony/security-core', '5.4', 'Method "%s()" is deprecated.', __METHOD__);

        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function __serialize(): array
    {
        return [$this->secret, $this->firewallName, parent::__serialize()];
    }

    /**
     * {@inheritdoc}
     */
    public function __unserialize(array $data): void
    {
        [$this->secret, $this->firewallName, $parentData] = $data;
        $parentData = \is_array($parentData) ? $parentData : unserialize($parentData);
        parent::__unserialize($parentData);
    }
}
