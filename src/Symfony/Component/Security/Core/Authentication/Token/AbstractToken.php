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

use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Base class for Token instances.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
abstract class AbstractToken implements TokenInterface
{
    private $user;
    private $roleNames = [];
    private $authenticated = false;
    private $attributes = [];

    /**
     * @param string[] $roles An array of roles
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(array $roles = [])
    {
        foreach ($roles as $role) {
            $this->roleNames[] = $role;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getRoleNames(): array
    {
        return $this->roleNames;
    }

    /**
     * {@inheritdoc}
     */
    public function getUsername(/* $legacy = true */)
    {
        if (1 === \func_num_args() && false === func_get_arg(0)) {
            return null;
        }

        trigger_deprecation('symfony/security-core', '5.3', 'Method "%s()" is deprecated, use getUserIdentifier() instead.', __METHOD__);

        if ($this->user instanceof UserInterface) {
            return method_exists($this->user, 'getUserIdentifier') ? $this->user->getUserIdentifier() : $this->user->getUsername();
        }

        return (string) $this->user;
    }

    /**
     * {@inheritdoc}
     */
    public function getUserIdentifier(): string
    {
        // method returns "null" in non-legacy mode if not overridden
        $username = $this->getUsername(false);
        if (null !== $username) {
            trigger_deprecation('symfony/security-core', '5.3', 'Method "%s::getUsername()" is deprecated, override "getUserIdentifier()" instead.', get_debug_type($this));
        }

        if ($this->user instanceof UserInterface) {
            // @deprecated since Symfony 5.3, change to $user->getUserIdentifier() in 6.0
            return method_exists($this->user, 'getUserIdentifier') ? $this->user->getUserIdentifier() : $this->user->getUsername();
        }

        return (string) $this->user;
    }

    /**
     * {@inheritdoc}
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * {@inheritdoc}
     */
    public function setUser($user)
    {
        if (!($user instanceof UserInterface || (\is_object($user) && method_exists($user, '__toString')) || \is_string($user))) {
            throw new \InvalidArgumentException('$user must be an instanceof UserInterface, an object implementing a __toString method, or a primitive string.');
        }

        if (!$user instanceof UserInterface) {
            trigger_deprecation('symfony/security-core', '5.4', 'Using an object that is not an instance of "%s" as $user in "%s" is deprecated.', UserInterface::class, static::class);
        }

        // @deprecated since Symfony 5.4, remove the whole block if/elseif/else block in 6.0
        if (1 < \func_num_args() && !func_get_arg(1)) {
            // ContextListener checks if the user has changed on its own and calls `setAuthenticated()` subsequently,
            // avoid doing the same checks twice
            $changed = false;
        } elseif (null === $this->user) {
            $changed = false;
        } elseif ($this->user instanceof UserInterface) {
            if (!$user instanceof UserInterface) {
                $changed = true;
            } else {
                $changed = $this->hasUserChanged($user);
            }
        } elseif ($user instanceof UserInterface) {
            $changed = true;
        } else {
            $changed = (string) $this->user !== (string) $user;
        }

        // @deprecated since Symfony 5.4
        if ($changed) {
            $this->setAuthenticated(false, false);
        }

        $this->user = $user;
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated since Symfony 5.4
     */
    public function isAuthenticated()
    {
        if (1 > \func_num_args() || func_get_arg(0)) {
            trigger_deprecation('symfony/security-core', '5.4', 'Method "%s()" is deprecated, return null from "getUser()" instead when a token is not authenticated.', __METHOD__);
        }

        return $this->authenticated;
    }

    /**
     * {@inheritdoc}
     */
    public function setAuthenticated(bool $authenticated)
    {
        if (2 > \func_num_args() || func_get_arg(1)) {
            trigger_deprecation('symfony/security-core', '5.4', 'Method "%s()" is deprecated', __METHOD__);
        }

        $this->authenticated = $authenticated;
    }

    /**
     * {@inheritdoc}
     */
    public function eraseCredentials()
    {
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
        return [$this->user, $this->authenticated, null, $this->attributes, $this->roleNames];
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
        [$this->user, $this->authenticated, , $this->attributes, $this->roleNames] = $data;
    }

    /**
     * {@inheritdoc}
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * {@inheritdoc}
     */
    public function setAttributes(array $attributes)
    {
        $this->attributes = $attributes;
    }

    /**
     * {@inheritdoc}
     */
    public function hasAttribute(string $name)
    {
        return \array_key_exists($name, $this->attributes);
    }

    /**
     * {@inheritdoc}
     */
    public function getAttribute(string $name)
    {
        if (!\array_key_exists($name, $this->attributes)) {
            throw new \InvalidArgumentException(sprintf('This token has no "%s" attribute.', $name));
        }

        return $this->attributes[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function setAttribute(string $name, $value)
    {
        $this->attributes[$name] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        $class = static::class;
        $class = substr($class, strrpos($class, '\\') + 1);

        $roles = [];
        foreach ($this->roleNames as $role) {
            $roles[] = $role;
        }

        return sprintf('%s(user="%s", authenticated=%s, roles="%s")', $class, $this->getUserIdentifier(), json_encode($this->authenticated), implode(', ', $roles));
    }

    /**
     * @internal
     */
    final public function serialize(): string
    {
        return serialize($this->__serialize());
    }

    /**
     * @internal
     */
    final public function unserialize($serialized)
    {
        $this->__unserialize(\is_array($serialized) ? $serialized : unserialize($serialized));
    }

    /**
     * @deprecated since Symfony 5.4
     */
    private function hasUserChanged(UserInterface $user): bool
    {
        if (!($this->user instanceof UserInterface)) {
            throw new \BadMethodCallException('Method "hasUserChanged" should be called when current user class is instance of "UserInterface".');
        }

        if ($this->user instanceof EquatableInterface) {
            return !(bool) $this->user->isEqualTo($user);
        }

        // @deprecated since Symfony 5.3, check for PasswordAuthenticatedUserInterface on both user objects before comparing passwords
        if ($this->user->getPassword() !== $user->getPassword()) {
            return true;
        }

        // @deprecated since Symfony 5.3, check for LegacyPasswordAuthenticatedUserInterface on both user objects before comparing salts
        if ($this->user->getSalt() !== $user->getSalt()) {
            return true;
        }

        $userRoles = array_map('strval', (array) $user->getRoles());

        if ($this instanceof SwitchUserToken) {
            $userRoles[] = 'ROLE_PREVIOUS_ADMIN';
        }

        if (\count($userRoles) !== \count($this->getRoleNames()) || \count($userRoles) !== \count(array_intersect($userRoles, $this->getRoleNames()))) {
            return true;
        }

        // @deprecated since Symfony 5.3, drop getUsername() in 6.0
        $userIdentifier = function ($user) {
            return method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : $user->getUsername();
        };
        if ($userIdentifier($this->user) !== $userIdentifier($user)) {
            return true;
        }

        return false;
    }
}
