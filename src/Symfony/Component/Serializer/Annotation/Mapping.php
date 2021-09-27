<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Annotation;

use Symfony\Component\Serializer\Exception\InvalidArgumentException;

/**
 * Annotation class for @Mapping().
 *
 * @Annotation
 * @Target({"CLASS"})
 *
 * @author Bertrand Seurot <b.seurot@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Mapping
{
    /**
     * @var AttributeConfiguration[]
     */
    private $attributes;

    /**
     * @var string[]
     */
    private $groups;

    /**
     * @var int | null
     */
    private $maxDepth;

    public const SUPPORTED_ATTRIBUTE_OPTIONS = ['name', 'groups', 'serializedName', 'maxDepth'];

    /**
     * @param string|array         $attributes
     * @param string|string[]|null $groups
     *
     * @throws InvalidArgumentException
     */
    public function __construct($attributes, $groups = null, int $maxDepth = null)
    {
        if (\is_array($attributes) && isset($attributes['attributes'])) {
            $groups = $attributes['groups'] ?? null;
            $maxDepth = $attributes['maxDepth'] ?? null;
            $attributes = $attributes['attributes'] ?? null;
        }

        if (null !== $attributes) {
            if (\is_string($attributes)) {
                $attributes = [new AttributeConfiguration($attributes)];
            } elseif (\is_array($attributes)) {
                $attributes = $this->getValidAttributesArray($attributes);
            } else {
                throw new \TypeError(sprintf('"%s": Argument "$attributes" was expected to be a string or array, got "%s".', __METHOD__, get_debug_type($attributes)));
            }
        }

        if (null !== $groups) {
            if (\is_string($groups)) {
                if (empty($groups)) {
                    throw new InvalidArgumentException(sprintf('Parameter "groups" of annotation "%s" must be a non-empty string or an array of non-empty strings.', static::class));
                }
                $groups = [$groups];
            } elseif (\is_array($groups)) {
                if (false === $this->isArrayOfNonEmptyStrings($groups)) {
                    throw new InvalidArgumentException(sprintf('Parameter "groups" of annotation "%s" must be a non-empty string or an array of non-empty strings.', static::class));
                }
            } else {
                throw new \TypeError(sprintf('"%s": Argument "$groups" was expected to be a string or array, got "%s".', __METHOD__, get_debug_type($groups)));
            }
        }

        if (null !== $maxDepth) {
            if (!\is_int($maxDepth)) {
                throw new \TypeError(sprintf('"%s": Argument $maxDepth was expected to be a string or array, got "%s".', __METHOD__, get_debug_type($maxDepth)));
            } elseif ($maxDepth <= 0) {
                throw new InvalidArgumentException(sprintf('Parameter "maxDepth" of annotation "%s" must be a positive integer.', static::class));
            }
        }

        if (empty($groups) && !$maxDepth) {
            foreach ($attributes as $attribute) {
                if (
                    empty($attribute->getGroups())
                    && null === $attribute->getMaxDepth()
                    && null === $attribute->getSerializedName()
                ) {
                    $parametersWithEffects = array_filter(self::SUPPORTED_ATTRIBUTE_OPTIONS, function ($item) {
                        return 'name' !== $item;
                    });
                    throw new InvalidArgumentException(sprintf('Attribute "%s" defined in annotation "%s" has none of the following parameters : "%s". Defining it will so have no effect.', $attribute->getName(), static::class, implode('", "', $parametersWithEffects)));
                }
            }
        }

        $this->attributes = $attributes;
        $this->groups = $groups ?? [];
        $this->maxDepth = $maxDepth;
    }

    /**
     * @return AttributeConfiguration[]
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    public function getGroups()
    {
        return $this->groups;
    }

    public function getMaxDepth()
    {
        return $this->maxDepth;
    }

    private function isArrayOfNonEmptyStrings(array $array): bool
    {
        foreach ($array as $item) {
            if (!\is_string($item) || empty($item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return AttributeConfiguration[]
     */
    private function getValidAttributesArray(array $attributes): array
    {
        $validAttributes = [];
        foreach ($attributes as $attribute) {
            if (\is_string($attribute)) {
                $validAttributes[] = new AttributeConfiguration($attribute);
                continue;
            }

            if (\is_array($attribute)) {
                $validAttributes[] = $this->getValidAttributeConfiguration($attribute);
                continue;
            }

            throw new InvalidArgumentException(sprintf('Parameter "groups" of annotation "%s" must be a string or an array. "%s" was met.', static::class, get_debug_type($attribute)));
        }

        return $validAttributes;
    }

    private function getValidAttributeConfiguration(array $attribute): AttributeConfiguration
    {
        $unsupportedKeys = array_diff(array_keys($attribute), self::SUPPORTED_ATTRIBUTE_OPTIONS);
        if (\count($unsupportedKeys)) {
            throw new InvalidArgumentException(sprintf('Unknown option found: [%s]. Allowed options are [%s].', implode(', ', $unsupportedKeys), implode(', ', self::SUPPORTED_ATTRIBUTE_OPTIONS)));
        }

        $attributeName = $attribute['name'] ?? null;
        if (empty($attributeName)) {
            throw new InvalidArgumentException(sprintf('In array defined attributes of annotation "%s", parameter "name" is required and cannot be empty.', static::class));
        }

        if (!\is_string($attributeName)) {
            throw new InvalidArgumentException(sprintf('In array defined attributes of annotation "%s", parameter "name" must be a string.', static::class));
        }

        $groups = $attribute['groups'] ?? null;
        if (null !== $groups) {
            if (\is_string($groups)) {
                $groups = [$groups];
            } elseif (!\is_array($groups) && !$this->isArrayOfNonEmptyStrings($groups)) {
                throw new InvalidArgumentException(sprintf('In array defined attributes of annotation "%s", parameter "groups" must be a string or an array of strings.', static::class));
            }
        }

        $serializedName = $attribute['serializedName'] ?? null;
        if (null !== $serializedName && (empty($serializedName) || !\is_string($serializedName))) {
            throw new InvalidArgumentException(sprintf('In array defined attributes of annotation "%s", parameter "serializedName" must be a non-empty string.', static::class));
        }

        $maxDepth = $attribute['maxDepth'] ?? null;
        if (null !== $maxDepth && (empty($maxDepth) || !\is_int($maxDepth))) {
            throw new InvalidArgumentException(sprintf('In array defined attributes of annotation "%s", parameter "maxDepth" must be a positive integer.', static::class));
        }

        return new AttributeConfiguration($attributeName, $groups, $maxDepth, $serializedName);
    }
}
