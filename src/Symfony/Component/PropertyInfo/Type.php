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
    public const BUILTIN_TYPE_TRUE = 'true';
    public const BUILTIN_TYPE_CALLABLE = 'callable';
    public const BUILTIN_TYPE_ITERABLE = 'iterable';

    /**
     * List of PHP builtin types.
     *
     * @var string[]
     */
    public static array $builtinTypes = [
        self::BUILTIN_TYPE_INT,
        self::BUILTIN_TYPE_FLOAT,
        self::BUILTIN_TYPE_STRING,
        self::BUILTIN_TYPE_BOOL,
        self::BUILTIN_TYPE_RESOURCE,
        self::BUILTIN_TYPE_OBJECT,
        self::BUILTIN_TYPE_ARRAY,
        self::BUILTIN_TYPE_CALLABLE,
        self::BUILTIN_TYPE_FALSE,
        self::BUILTIN_TYPE_TRUE,
        self::BUILTIN_TYPE_NULL,
        self::BUILTIN_TYPE_ITERABLE,
    ];

    /**
     * List of PHP builtin collection types.
     *
     * @var string[]
     */
    public static array $builtinCollectionTypes = [
        self::BUILTIN_TYPE_ARRAY,
        self::BUILTIN_TYPE_ITERABLE,
    ];

    private string $builtinType;
    private bool $nullable;
    private ?string $class;
    private bool $collection;
    private array $collectionKeyType;
    private array $collectionValueType;

    /**
     * @param Type[]|Type|null $collectionKeyType
     * @param Type[]|Type|null $collectionValueType
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(string $builtinType, bool $nullable = false, ?string $class = null, bool $collection = false, array|self|null $collectionKeyType = null, array|self|null $collectionValueType = null)
    {
        if (!\in_array($builtinType, self::$builtinTypes)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid PHP type.', $builtinType));
        }

        $this->builtinType = $builtinType;
        $this->nullable = $nullable;
        $this->class = $class;
        $this->collection = $collection;
        $this->collectionKeyType = $this->validateCollectionArgument($collectionKeyType, 5, '$collectionKeyType') ?? [];
        $this->collectionValueType = $this->validateCollectionArgument($collectionValueType, 6, '$collectionValueType') ?? [];
    }

    private function validateCollectionArgument(array|self|null $collectionArgument, int $argumentIndex, string $argumentName): ?array
    {
        if (null === $collectionArgument) {
            return null;
        }

        if (\is_array($collectionArgument)) {
            foreach ($collectionArgument as $type) {
                if (!$type instanceof self) {
                    throw new \TypeError(sprintf('"%s()": Argument #%d (%s) must be of type "%s[]", "%s" or "null", array value "%s" given.', __METHOD__, $argumentIndex, $argumentName, self::class, self::class, get_debug_type($collectionArgument)));
                }
            }

            return $collectionArgument;
        }

        return [$collectionArgument];
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
     * Gets collection key types.
     *
     * Only applicable for a collection type.
     *
     * @return Type[]
     */
    public function getCollectionKeyTypes(): array
    {
        return $this->collectionKeyType;
    }

    /**
     * Gets collection value types.
     *
     * Only applicable for a collection type.
     *
     * @return Type[]
     */
    public function getCollectionValueTypes(): array
    {
        return $this->collectionValueType;
    }
}
