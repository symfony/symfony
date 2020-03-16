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

use Symfony\Component\Security\Core\Role\Role;
use Symfony\Component\Security\Core\User\AdvancedUserInterface;
use Symfony\Component\Security\Core\User\EquatableInterface;
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
    private $roles = [];
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
            if (\is_string($role)) {
                $role = new Role($role, false);
            } elseif (!$role instanceof Role) {
                throw new \InvalidArgumentException(sprintf('$roles must be an array of strings, but got "%s".', \gettype($role)));
            }

            $this->roles[] = $role;
            $this->roleNames[] = (string) $role;
        }
    }

    public function getRoleNames(): array
    {
        return $this->roleNames;
    }

    /**
     * {@inheritdoc}
     */
    public function getRoles()
    {
        if (0 === \func_num_args() || func_get_arg(0)) {
            @trigger_error(sprintf('The %s() method is deprecated since Symfony 4.3. Use the getRoleNames() method instead.', __METHOD__), E_USER_DEPRECATED);
        }

        return $this->roles;
    }

    /**
     * {@inheritdoc}
     */
    public function getUsername()
    {
        if ($this->user instanceof UserInterface) {
            return $this->user->getUsername();
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

        if (null === $this->user) {
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

        if ($changed) {
            $this->setAuthenticated(false);
        }

        $this->user = $user;
    }

    /**
     * {@inheritdoc}
     */
    public function isAuthenticated()
    {
        return $this->authenticated;
    }

    /**
     * {@inheritdoc}
     */
    public function setAuthenticated($authenticated)
    {
        $this->authenticated = (bool) $authenticated;
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
        return [$this->user, $this->authenticated, $this->roles, $this->attributes, $this->roleNames];
    }

    /**
     * @return string
     *
     * @final since Symfony 4.3, use __serialize() instead
     *
     * @internal since Symfony 4.3, use __serialize() instead
     */
    public function serialize()
    {
        $serialized = $this->__serialize();

        if (null === $isCalledFromOverridingMethod = \func_num_args() ? func_get_arg(0) : null) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 2);
            $isCalledFromOverridingMethod = isset($trace[1]['function'], $trace[1]['object']) && 'serialize' === $trace[1]['function'] && $this === $trace[1]['object'];
        }

        return $isCalledFromOverridingMethod ? $serialized : serialize($serialized);
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
        [$this->user, $this->authenticated, $this->roles, $this->attributes] = $data;

        // migration path to 4.3+
        if (null === $this->roleNames = $data[4] ?? null) {
            $this->roleNames = [];
            foreach ($this->roles as $role) {
                $this->roleNames[] = (string) $role;
            }
        }
    }

    /**
     * {@inheritdoc}
     *
     * @final since Symfony 4.3, use __unserialize() instead
     *
     * @internal since Symfony 4.3, use __unserialize() instead
     */
    public function unserialize($serialized)
    {
        $this->__unserialize(\is_array($serialized) ? $serialized : unserialize($serialized));
    }

    /**
     * Returns the token attributes.
     *
     * @return array The token attributes
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Sets the token attributes.
     *
     * @param array $attributes The token attributes
     */
    public function setAttributes(array $attributes)
    {
        $this->attributes = $attributes;
    }

    /**
     * Returns true if the attribute exists.
     *
     * @param string $name The attribute name
     *
     * @return bool true if the attribute exists, false otherwise
     */
    public function hasAttribute($name)
    {
        return \array_key_exists($name, $this->attributes);
    }

    /**
     * Returns an attribute value.
     *
     * @param string $name The attribute name
     *
     * @return mixed The attribute value
     *
     * @throws \InvalidArgumentException When attribute doesn't exist for this token
     */
    public function getAttribute($name)
    {
        if (!\array_key_exists($name, $this->attributes)) {
            throw new \InvalidArgumentException(sprintf('This token has no "%s" attribute.', $name));
        }

        return $this->attributes[$name];
    }

    /**
     * Sets an attribute.
     *
     * @param string $name  The attribute name
     * @param mixed  $value The attribute value
     */
    public function setAttribute($name, $value)
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
        foreach ($this->roles as $role) {
            $roles[] = $role->getRole();
        }

        return sprintf('%s(user="%s", authenticated=%s, roles="%s")', $class, $this->getUsername(), json_encode($this->authenticated), implode(', ', $roles));
    }

    private function hasUserChanged(UserInterface $user): bool
    {
        if (!($this->user instanceof UserInterface)) {
            throw new \BadMethodCallException('Method "hasUserChanged" should be called when current user class is instance of "UserInterface".');
        }

        if ($this->user instanceof EquatableInterface) {
            return !(bool) $this->user->isEqualTo($user);
        }

        if ($this->user->getPassword() !== $user->getPassword()) {
            return true;
        }

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

        if ($this->user->getUsername() !== $user->getUsername()) {
            return true;
        }

        if ($this->user instanceof AdvancedUserInterface && $user instanceof AdvancedUserInterface) {
            @trigger_error(sprintf('Checking for the AdvancedUserInterface in "%s()" is deprecated since Symfony 4.1 and support for it will be removed in 5.0. Implement the %s to check if the user has been changed,', __METHOD__, EquatableInterface::class), E_USER_DEPRECATED);
            if ($this->user->isAccountNonExpired() !== $user->isAccountNonExpired()) {
                return true;
            }

            if ($this->user->isAccountNonLocked() !== $user->isAccountNonLocked()) {
                return true;
            }

            if ($this->user->isCredentialsNonExpired() !== $user->isCredentialsNonExpired()) {
                return true;
            }

            if ($this->user->isEnabled() !== $user->isEnabled()) {
                return true;
            }
        } elseif ($this->user instanceof AdvancedUserInterface xor $user instanceof AdvancedUserInterface) {
            @trigger_error(sprintf('Checking for the AdvancedUserInterface in "%s()" is deprecated since Symfony 4.1 and support for it will be removed in 5.0. Implement the %s to check if the user has been changed,', __METHOD__, EquatableInterface::class), E_USER_DEPRECATED);

            return true;
        }

        return false;
    }
}
