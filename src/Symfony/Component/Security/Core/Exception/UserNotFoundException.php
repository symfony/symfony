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
    private ?string $identifier = null;

    /**
     * {@inheritdoc}
     */
    public function getMessageKey(): string
    {
        return 'Username could not be found.';
    }

    /**
     * Get the user identifier (e.g. username or email address).
     */
    public function getUserIdentifier(): ?string
    {
        return $this->identifier;
    }

    /**
     * Set the user identifier (e.g. username or email address).
     */
    public function setUserIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    /**
     * {@inheritdoc}
     */
    public function getMessageData(): array
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
