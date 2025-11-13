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

use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Base class for Token instances.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
abstract class AbstractToken implements TokenInterface, \Serializable
{
    private ?UserInterface $user = null;
    private array $roleNames;
    private array $attributes = [];

    /**
     * @param string[] $roles An array of roles
     */
    public function __construct(array $roles = [])
    {
        $this->roleNames = $roles;
    }

    public function getRoleNames(): array
    {
        return $this->roleNames ??= $this->user?->getRoles() ?? [];
    }

    public function getUserIdentifier(): string
    {
        return $this->user ? $this->user->getUserIdentifier() : '';
    }

    public function getUser(): ?UserInterface
    {
        return $this->user;
    }

    public function setUser(UserInterface $user): void
    {
        $this->user = $user;
    }

    /**
     * Removes sensitive information from the token.
     *
     * @deprecated since Symfony 7.3, erase credentials using the "__serialize()" method instead
     */
    public function eraseCredentials(): void
    {
        trigger_deprecation('symfony/security-core', '7.3', \sprintf('The "%s::eraseCredentials()" method is deprecated and will be removed in 8.0, erase credentials using the "__serialize()" method instead.', TokenInterface::class));

        if ($this->getUser() instanceof UserInterface) {
            $this->getUser()->eraseCredentials();
        }
    }

    /**
     * Returns all the necessary state of the object for serialization purposes.
     *
     * There is no need to serialize any entry, they should be returned as-is.
     * If you extend this method, keep in mind you MUST guarantee parent data is present in the state.
     * Here is an example of how to extend this method:
     * <code>
     *     public function __serialize(): array
     *     {
     *         return [$this->childAttribute, parent::__serialize()];
     *     }
     * </code>
     *
     * @see __unserialize()
     */
    public function __serialize(): array
    {
        return [$this->user, true, null, $this->attributes, $this->getRoleNames()];
    }

    /**
     * Restores the object state from an array given by __serialize().
     *
     * There is no need to unserialize any entry in $data, they are already ready-to-use.
     * If you extend this method, keep in mind you MUST pass the parent data to its respective class.
     * Here is an example of how to extend this method:
     * <code>
     *     public function __unserialize(array $data): void
     *     {
     *         [$this->childAttribute, $parentData] = $data;
     *         parent::__unserialize($parentData);
     *     }
     * </code>
     *
     * @see __serialize()
     */
    public function __unserialize(array $data): void
    {
        [$user, , , $this->attributes] = $data;

        if (\array_key_exists(4, $data)) {
            $this->roleNames = $data[4];
        }

        $this->user = \is_string($user) ? new InMemoryUser($user, '', $this->roleNames, false) : $user;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function setAttributes(array $attributes): void
    {
        $this->attributes = $attributes;
    }

    public function hasAttribute(string $name): bool
    {
        return \array_key_exists($name, $this->attributes);
    }

    public function getAttribute(string $name): mixed
    {
        if (!\array_key_exists($name, $this->attributes)) {
            throw new \InvalidArgumentException(\sprintf('This token has no "%s" attribute.', $name));
        }

        return $this->attributes[$name];
    }

    public function setAttribute(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function __toString(): string
    {
        $class = static::class;
        $class = substr($class, strrpos($class, '\\') + 1);

        return \sprintf('%s(user="%s", roles="%s")', $class, $this->getUserIdentifier(), implode(', ', $this->getRoleNames()));
    }

    /**
     * @internal
     */
    final public function serialize(): string
    {
        throw new \BadMethodCallException('Cannot serialize '.__CLASS__);
    }

    /**
     * @internal
     */
    final public function unserialize(string $serialized): void
    {
        $this->__unserialize(unserialize($serialized));
    }
}
