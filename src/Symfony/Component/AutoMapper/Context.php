<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AutoMapper;

use Symfony\Component\AutoMapper\Exception\CircularReferenceException;

/**
 * Context for mapping.
 *
 * Allow to customize how is done the mapping
 *
 * @author Joel Wurtz <jwurtz@jolicode.com>
 */
class Context extends \ArrayObject
{
    private $referenceRegistry = [];

    private $countReferenceRegistry = [];

    private $groups;

    private $depth;

    private $object;

    private $circularReferenceLimit;

    private $circularReferenceHandler;

    private $attributes;

    private $ignoredAttributes;

    private $constructorArguments = [];

    /**
     * @param array|null $groups            Groups to use for mapping
     * @param array|null $attributes        Attributes to use for mapping (exclude others)
     * @param array|null $ignoredAttributes Attributes to exclude from mapping (include others)
     */
    public function __construct(array $groups = null, array $attributes = null, array $ignoredAttributes = null)
    {
        parent::__construct();

        $this->groups = $groups;
        $this->depth = 0;
        $this->attributes = $attributes;
        $this->ignoredAttributes = $ignoredAttributes;
    }

    /**
     * Whether a reference has reached it's limit.
     */
    public function shouldHandleCircularReference(string $reference, ?int $circularReferenceLimit = null): bool
    {
        if (!isset($this->referenceRegistry[$reference])) {
            return false;
        }

        if (null === $circularReferenceLimit) {
            $circularReferenceLimit = $this->circularReferenceLimit;
        }

        if (null !== $circularReferenceLimit) {
            return $this->countReferenceRegistry[$reference] >= $circularReferenceLimit;
        }

        return true;
    }

    /**
     * Handle circular reference for a specific reference.
     *
     * By default will try to keep it and return the previous value
     *
     * @return mixed
     */
    public function &handleCircularReference(string $reference, $object, ?int $circularReferenceLimit = null, callable $callback = null)
    {
        if (null === $callback) {
            $callback = $this->circularReferenceHandler;
        }

        if (null !== $callback) {
            $value = $callback($object, $this);

            return $value;
        }

        if (null === $circularReferenceLimit) {
            $circularReferenceLimit = $this->circularReferenceLimit;
        }

        if (null !== $circularReferenceLimit && $this->countReferenceRegistry[$reference] >= $circularReferenceLimit) {
            throw new CircularReferenceException(sprintf('A circular reference has been detected when mapping the object of type "%s" (configured limit: %d)', \is_object($object) ? \get_class($object) : 'array', $circularReferenceLimit));
        }

        // When no limit defined return the object referenced
        ++$this->countReferenceRegistry[$reference];

        return $this->referenceRegistry[$reference];
    }

    /**
     * Get groups for this context.
     */
    public function getGroups(): ?array
    {
        return $this->groups;
    }

    /**
     * Get current depth.
     */
    public function getDepth(): int
    {
        return $this->depth;
    }

    /**
     * Set object to populate (by-pass target construction).
     */
    public function setObjectToPopulate($object)
    {
        $this->object = $object;
    }

    /**
     * Get object to populate.
     */
    public function getObjectToPopulate()
    {
        $object = $this->object;

        if (null !== $object) {
            $this->object = null;
        }

        return $object;
    }

    /**
     * Set circular reference limit.
     */
    public function setCircularReferenceLimit(?int $circularReferenceLimit): void
    {
        $this->circularReferenceLimit = $circularReferenceLimit;
    }

    /**
     * Set circular reference handler.
     */
    public function setCircularReferenceHandler(?callable $circularReferenceHandler): void
    {
        $this->circularReferenceHandler = $circularReferenceHandler;
    }

    /**
     * Create a new context with a new reference.
     */
    public function withReference($reference, &$object): self
    {
        $new = clone $this;

        $new->referenceRegistry[$reference] = &$object;
        $new->countReferenceRegistry[$reference] = 1;

        return $new;
    }

    /**
     * Check whether an attribute is allowed to be mapped.
     */
    public function isAllowedAttribute(string $attribute): bool
    {
        if (null !== $this->ignoredAttributes && \in_array($attribute, $this->ignoredAttributes, true)) {
            return false;
        }

        if (null === $this->attributes) {
            return true;
        }

        return \in_array($attribute, $this->attributes, true);
    }

    /**
     * Clone context with a incremented depth.
     */
    public function withIncrementedDepth(): self
    {
        $new = clone $this;
        ++$new->depth;

        return $new;
    }

    /**
     * Set the argument of a constructor for a specific class.
     */
    public function setConstructorArgument(string $class, string $key, $value): void
    {
        if ($this->constructorArguments[$class] ?? false) {
            $this->constructorArguments[$class] = [];
        }

        $this->constructorArguments[$class][$key] = $value;
    }

    /**
     * Check wether an argument exist for the constructor for a specific class.
     */
    public function hasConstructorArgument(string $class, string $key): bool
    {
        return \array_key_exists($key, $this->constructorArguments[$class] ?? []);
    }

    /**
     * Get constructor argument for a specific class.
     */
    public function getConstructorArgument(string $class, string $key)
    {
        return $this->constructorArguments[$class][$key];
    }

    /**
     * Create a new cloned context, and reload attribute mapping for it.
     */
    public function withNewContext(string $attribute): self
    {
        if (null === $this->attributes) {
            return $this;
        }

        $new = clone $this;

        if (null !== $this->ignoredAttributes && isset($this->ignoredAttributes[$attribute]) && \is_array($this->ignoredAttributes[$attribute])) {
            $new->ignoredAttributes = $this->ignoredAttributes[$attribute];
        }

        if (null !== $this->attributes && isset($this->attributes[$attribute]) && \is_array($this->attributes[$attribute])) {
            $new->attributes = $this->attributes[$attribute];
        }

        return $new;
    }
}
