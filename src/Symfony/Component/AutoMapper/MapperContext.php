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
 * Allows to customize how is done the mapping
 *
 * @author Joel Wurtz <jwurtz@jolicode.com>
 */
class MapperContext
{
    public const GROUPS = 'groups';
    public const ALLOWED_ATTRIBUTES = 'allowed_attributes';
    public const IGNORED_ATTRIBUTES = 'ignored_attributes';
    public const CIRCULAR_REFERENCE_LIMIT = 'circular_reference_limit';
    public const CIRCULAR_REFERENCE_HANDLER = 'circular_reference_handler';
    public const CIRCULAR_REFERENCE_REGISTRY = 'circular_reference_registry';
    public const CIRCULAR_COUNT_REFERENCE_REGISTRY = 'circular_count_reference_registry';
    public const DEPTH = 'depth';
    public const TARGET_TO_POPULATE = 'target_to_populate';
    public const CONSTRUCTOR_ARGUMENTS = 'constructor_arguments';

    private $context = [
        self::DEPTH => 0,
        self::CIRCULAR_REFERENCE_REGISTRY => [],
        self::CIRCULAR_COUNT_REFERENCE_REGISTRY => [],
        self::CONSTRUCTOR_ARGUMENTS => [],
    ];

    public function toArray(): array
    {
        return $this->context;
    }

    /**
     * @return $this
     */
    public function setGroups(?array $groups)
    {
        $this->context[self::GROUPS] = $groups;

        return $this;
    }

    /**
     * @return $this
     */
    public function setAllowedAttributes(?array $allowedAttributes)
    {
        $this->context[self::ALLOWED_ATTRIBUTES] = $allowedAttributes;

        return $this;
    }

    /**
     * @return $this
     */
    public function setIgnoredAttributes(?array $ignoredAttributes)
    {
        $this->context[self::IGNORED_ATTRIBUTES] = $ignoredAttributes;

        return $this;
    }

    /**
     * @return $this
     */
    public function setCircularReferenceLimit(?int $circularReferenceLimit)
    {
        $this->context[self::CIRCULAR_REFERENCE_LIMIT] = $circularReferenceLimit;

        return $this;
    }

    /**
     * @return $this
     */
    public function setCircularReferenceHandler(?callable $circularReferenceHandler)
    {
        $this->context[self::CIRCULAR_REFERENCE_HANDLER] = $circularReferenceHandler;

        return $this;
    }

    /**
     * @return $this
     */
    public function setTargetToPopulate($target)
    {
        $this->context[self::TARGET_TO_POPULATE] = $target;

        return $this;
    }

    /**
     * @return $this
     */
    public function setConstructorArgument(string $class, string $key, $value): void
    {
        $this->context[self::CONSTRUCTOR_ARGUMENTS][$class][$key] = $value;
    }

    /**
     * Whether a reference has reached it's limit.
     */
    public static function shouldHandleCircularReference(array $context, string $reference, ?int $circularReferenceLimit = null): bool
    {
        if (!\array_key_exists($reference, $context[self::CIRCULAR_REFERENCE_REGISTRY] ?? [])) {
            return false;
        }

        if (null === $circularReferenceLimit) {
            $circularReferenceLimit = $context[self::CIRCULAR_REFERENCE_LIMIT] ?? null;
        }

        if (null !== $circularReferenceLimit) {
            return $circularReferenceLimit <= ($context[self::CIRCULAR_COUNT_REFERENCE_REGISTRY][$reference] ?? 0);
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
    public static function &handleCircularReference(array $context, string $reference, $object, ?int $circularReferenceLimit = null, callable $callback = null)
    {
        if (null === $callback) {
            $callback = $context[self::CIRCULAR_REFERENCE_HANDLER] ?? null;
        }

        if (null !== $callback) {
            // Cannot directly return here, as we need to return by reference, and callback may not be declared as reference return
            $value = $callback($object, $context);

            return $value;
        }

        if (null === $circularReferenceLimit) {
            $circularReferenceLimit = $context[self::CIRCULAR_REFERENCE_LIMIT] ?? null;
        }

        if (null !== $circularReferenceLimit && $circularReferenceLimit <= ($context[self::CIRCULAR_COUNT_REFERENCE_REGISTRY][$reference] ?? 0)) {
            throw new CircularReferenceException(sprintf('A circular reference has been detected when mapping the object of type "%s" (configured limit: %d)', \is_object($object) ? \get_class($object) : 'array', $circularReferenceLimit));
        }

        // When no limit defined return the object referenced
        return $context[self::CIRCULAR_REFERENCE_REGISTRY][$reference];
    }

    /**
     * Create a new context with a new reference.
     */
    public static function withReference(array $context, string $reference, &$object): array
    {
        $context[self::CIRCULAR_REFERENCE_REGISTRY][$reference] = &$object;
        $context[self::CIRCULAR_COUNT_REFERENCE_REGISTRY][$reference] = $context[self::CIRCULAR_COUNT_REFERENCE_REGISTRY][$reference] ?? 0;
        $context[self::CIRCULAR_COUNT_REFERENCE_REGISTRY][$reference]++;

        return $context;
    }

    /**
     * Check whether an attribute is allowed to be mapped.
     */
    public static function isAllowedAttribute(array $context, string $attribute): bool
    {
        if (($context[self::IGNORED_ATTRIBUTES] ?? false) && \in_array($attribute, $context[self::IGNORED_ATTRIBUTES], true)) {
            return false;
        }

        if (!($context[self::ALLOWED_ATTRIBUTES] ?? false)) {
            return true;
        }

        return \in_array($attribute, $context[self::ALLOWED_ATTRIBUTES], true);
    }

    /**
     * Clone context with a incremented depth.
     */
    public static function withIncrementedDepth(array $context): array
    {
        $context[self::DEPTH] = $context[self::DEPTH] ?? 0;
        $context[self::DEPTH]++;

        return $context;
    }

    /**
     * Check wether an argument exist for the constructor for a specific class.
     */
    public static function hasConstructorArgument(array $context, string $class, string $key): bool
    {
        return \array_key_exists($key, $context[self::CONSTRUCTOR_ARGUMENTS][$class] ?? []);
    }

    /**
     * Get constructor argument for a specific class.
     */
    public static function getConstructorArgument(array $context, string $class, string $key)
    {
        return $context[self::CONSTRUCTOR_ARGUMENTS][$class][$key] ?? null;
    }

    /**
     * Create a new context, and reload attribute mapping for it.
     */
    public static function withNewContext(array $context, string $attribute): array
    {
        if (!($context[self::ALLOWED_ATTRIBUTES] ?? false) && !($context[self::IGNORED_ATTRIBUTES] ?? false)) {
            return $context;
        }

        if (\is_array($context[self::IGNORED_ATTRIBUTES][$attribute] ?? false)) {
            $context[self::IGNORED_ATTRIBUTES] = $context[self::IGNORED_ATTRIBUTES][$attribute];
        }

        if (\is_array($context[self::ALLOWED_ATTRIBUTES][$attribute] ?? false)) {
            $context[self::ALLOWED_ATTRIBUTES] = $context[self::ALLOWED_ATTRIBUTES][$attribute];
        }

        return $context;
    }
}
