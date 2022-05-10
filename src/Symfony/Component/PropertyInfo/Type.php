<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo;

/**
 * Type value object (immutable).
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 *
 * @final
 */
class Type
{
    public const BUILTIN_TYPE_INT = 'int';
    public const BUILTIN_TYPE_FLOAT = 'float';
    public const BUILTIN_TYPE_STRING = 'string';
    public const BUILTIN_TYPE_BOOL = 'bool';
    public const BUILTIN_TYPE_RESOURCE = 'resource';
    public const BUILTIN_TYPE_OBJECT = 'object';
    public const BUILTIN_TYPE_ARRAY = 'array';
    public const BUILTIN_TYPE_NULL = 'null';
    public const BUILTIN_TYPE_FALSE = 'false';
    public const BUILTIN_TYPE_CALLABLE = 'callable';
    public const BUILTIN_TYPE_ITERABLE = 'iterable';

    /**
     * List of PHP builtin types.
     *
     * @var string[]
     */
    public static $builtinTypes = [
        self::BUILTIN_TYPE_INT,
        self::BUILTIN_TYPE_FLOAT,
        self::BUILTIN_TYPE_STRING,
        self::BUILTIN_TYPE_BOOL,
        self::BUILTIN_TYPE_RESOURCE,
        self::BUILTIN_TYPE_OBJECT,
        self::BUILTIN_TYPE_ARRAY,
        self::BUILTIN_TYPE_CALLABLE,
        self::BUILTIN_TYPE_FALSE,
        self::BUILTIN_TYPE_NULL,
        self::BUILTIN_TYPE_ITERABLE,
    ];

    private $builtinType;
    private $nullable;
    private $class;
    private $collection;
    private $collectionKeyType;
    private $collectionValueType;

    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(string $builtinType, bool $nullable = false, string $class = null, bool $collection = false, self $collectionKeyType = null, self $collectionValueType = null)
    {
        if (!\in_array($builtinType, self::$builtinTypes)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid PHP type.', $builtinType));
        }

        $this->builtinType = $builtinType;
        $this->nullable = $nullable;
        $this->class = $class;
        $this->collection = $collection;
        $this->collectionKeyType = $collectionKeyType;
        $this->collectionValueType = $collectionValueType;
    }

    /**
     * Gets built-in type.
     *
     * Can be bool, int, float, string, array, object, resource, null, callback or iterable.
     */
    public function getBuiltinType(): string
    {
        return $this->builtinType;
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }

    /**
     * Gets the class name.
     *
     * Only applicable if the built-in type is object.
     */
    public function getClassName(): ?string
    {
        return $this->class;
    }

    public function isCollection(): bool
    {
        return $this->collection;
    }

    /**
     * Gets collection key type.
     *
     * Only applicable for a collection type.
     */
    public function getCollectionKeyType(): ?self
    {
        return $this->collectionKeyType;
    }

    /**
     * Gets collection value type.
     *
     * Only applicable for a collection type.
     */
    public function getCollectionValueType(): ?self
    {
        return $this->collectionValueType;
    }
}
