<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Exception;

/**
 * UserNotFoundException is thrown if a User cannot be found for the given identifier.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Alexander <iam.asm89@gmail.com>
 */
class UserNotFoundException extends AuthenticationException
{
    private $identifier;

    /**
     * {@inheritdoc}
     */
    public function getMessageKey()
    {
        return 'Username could not be found.';
    }

    /**
     * Get the user identifier (e.g. username or e-mailaddress).
     */
    public function getUserIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * @return string
     *
     * @deprecated
     */
    public function getUsername()
    {
        trigger_deprecation('symfony/security-core', '5.3', 'Method "%s()" is deprecated, use getUserIdentifier() instead.', __METHOD__);

        return $this->identifier;
    }

    /**
     * Set the user identifier (e.g. username or e-mailaddress).
     */
    public function setUserIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    /**
     * @deprecated
     */
    public function setUsername(string $username)
    {
        trigger_deprecation('symfony/security-core', '5.3', 'Method "%s()" is deprecated, use setUserIdentifier() instead.', __METHOD__);

        $this->identifier = $username;
    }

    /**
     * {@inheritdoc}
     */
    public function getMessageData()
    {
        return ['{{ username }}' => $this->identifier, '{{ user_identifier }}' => $this->identifier];
    }

    /**
     * {@inheritdoc}
     */
    public function __serialize(): array
    {
        return [$this->identifier, parent::__serialize()];
    }

    /**
     * {@inheritdoc}
     */
    public function __unserialize(array $data): void
    {
        [$this->identifier, $parentData] = $data;
        $parentData = \is_array($parentData) ? $parentData : unserialize($parentData);
        parent::__unserialize($parentData);
    }
}

if (!class_exists(UsernameNotFoundException::class, false)) {
    class_alias(UserNotFoundException::class, UsernameNotFoundException::class);
}
