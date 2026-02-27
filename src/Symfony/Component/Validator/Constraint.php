<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator;

use Symfony\Component\Validator\Exception\InvalidArgumentException;
use Symfony\Component\Validator\Exception\InvalidOptionsException;

/**
 * Contains the properties of a constraint definition.
 *
 * A constraint can be defined on a class, a property or a getter method.
 * The Constraint class encapsulates all the configuration required for
 * validating this class, property or getter result successfully.
 *
 * Constraint instances are immutable and serializable.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class Constraint
{
    /**
     * The name of the group given to all constraints with no explicit group.
     */
    public const DEFAULT_GROUP = 'Default';

    /**
     * Marks a constraint that can be put onto classes.
     */
    public const CLASS_CONSTRAINT = 'class';

    /**
     * Marks a constraint that can be put onto properties.
     */
    public const PROPERTY_CONSTRAINT = 'property';

    /**
     * Maps error codes to the names of their constants.
     *
     * @var array<string, string>
     */
    protected const ERROR_NAMES = [];

    /**
     * Domain-specific data attached to a constraint.
     */
    public mixed $payload;

    /**
     * The groups that the constraint belongs to.
     *
     * @var string[]
     */
    public ?array $groups = null;

    /**
     * Returns the name of the given error code.
     *
     * @throws InvalidArgumentException If the error code does not exist
     */
    public static function getErrorName(string $errorCode): string
    {
        if (isset(static::ERROR_NAMES[$errorCode])) {
            return static::ERROR_NAMES[$errorCode];
        }

        throw new InvalidArgumentException(\sprintf('The error code "%s" does not exist for constraint of type "%s".', $errorCode, static::class));
    }

    /**
     * Initializes the constraint with the groups and payload options.
     *
     * @param string[] $groups  An array of validation groups
     * @param mixed    $payload Domain-specific data attached to a constraint
     */
    public function __construct(mixed $options = null, ?array $groups = null, mixed $payload = null)
    {
        unset($this->groups); // enable lazy initialization

        if (null !== $groups) {
            $this->groups = $groups;
        }

        $this->payload = $payload;
    }

    /**
     * Sets the value of a lazily initialized option.
     *
     * Corresponding properties are added to the object on first access. Hence
     * this method will be called at most once per constraint instance and
     * option name.
     *
     * @throws InvalidOptionsException If an invalid option name is given
     */
    public function __set(string $option, mixed $value): void
    {
        if ('groups' === $option) {
            $this->groups = (array) $value;

            return;
        }

        throw new InvalidOptionsException(\sprintf('The option "%s" does not exist in constraint "%s".', $option, static::class), [$option]);
    }

    /**
     * Returns the value of a lazily initialized option.
     *
     * Corresponding properties are added to the object on first access. Hence
     * this method will be called at most once per constraint instance and
     * option name.
     *
     * @throws InvalidOptionsException If an invalid option name is given
     */
    public function __get(string $option): mixed
    {
        if ('groups' === $option) {
            $this->groups = [self::DEFAULT_GROUP];

            return $this->groups;
        }

        throw new InvalidOptionsException(\sprintf('The option "%s" does not exist in constraint "%s".', $option, static::class), [$option]);
    }

    public function __isset(string $option): bool
    {
        return 'groups' === $option;
    }

    /**
     * Adds the given group if this constraint is in the Default group.
     */
    public function addImplicitGroupName(string $group): void
    {
        if (null === $this->groups && \array_key_exists('groups', (array) $this)) {
            throw new \LogicException(\sprintf('"%s::$groups" is set to null. Did you forget to call "%s::__construct()"?', static::class, self::class));
        }

        if (\in_array(self::DEFAULT_GROUP, $this->groups) && !\in_array($group, $this->groups, true)) {
            $this->groups[] = $group;
        }
    }

    /**
     * Returns the name of the class that validates this constraint.
     *
     * By default, this is the fully qualified name of the constraint class
     * suffixed with "Validator". You can override this method to change that
     * behavior.
     */
    public function validatedBy(): string
    {
        return static::class.'Validator';
    }

    /**
     * Returns whether the constraint can be put onto classes, properties or
     * both.
     *
     * @return self::CLASS_CONSTRAINT|self::PROPERTY_CONSTRAINT|array<self::CLASS_CONSTRAINT|self::PROPERTY_CONSTRAINT>
     */
    public function getTargets(): string|array
    {
        return self::PROPERTY_CONSTRAINT;
    }

    /**
     * Optimizes the serialized value to minimize storage space.
     */
    public function __serialize(): array
    {
        // Initialize "groups" option if it is not set
        $this->groups;

        $data = [];
        $class = $this::class;
        foreach ((array) $this as $k => $v) {
            $data[match (true) {
                '' === $k || "\0" !== $k[0] => $k,
                str_starts_with($k, "\0*\0") => substr($k, 3),
                str_starts_with($k, "\0{$class}\0") => substr($k, 2 + \strlen($class)),
                default => $k,
            }] = $v;
        }

        return $data;
    }
}
