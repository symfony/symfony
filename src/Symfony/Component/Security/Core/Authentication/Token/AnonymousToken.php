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
 * AnonymousToken represents an anonymous token.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since 5.4, anonymous is now represented by the absence of a token
 */
class AnonymousToken extends AbstractToken
{
    private $secret;

    /**
     * @param string                           $secret A secret used to make sure the token is created by the app and not by a malicious client
     * @param string|\Stringable|UserInterface $user
     * @param string[]                         $roles
     */
    public function __construct(string $secret, $user, array $roles = [])
    {
        trigger_deprecation('symfony/security-core', '5.4', 'The "%s" class is deprecated.', __CLASS__);

        parent::__construct($roles);

        $this->secret = $secret;
        $this->setUser($user);
        // @deprecated since Symfony 5.4
        $this->setAuthenticated(true, false);
    }

    /**
     * {@inheritdoc}
     */
    public function getCredentials()
    {
        return '';
    }

    /**
     * Returns the secret.
     *
     * @return string
     */
    public function getSecret()
    {
        return $this->secret;
    }

    /**
     * {@inheritdoc}
     */
    public function __serialize(): array
    {
        return [$this->secret, parent::__serialize()];
    }

    /**
     * {@inheritdoc}
     */
    public function __unserialize(array $data): void
    {
        [$this->secret, $parentData] = $data;
        $parentData = \is_array($parentData) ? $parentData : unserialize($parentData);
        parent::__unserialize($parentData);
    }
}
