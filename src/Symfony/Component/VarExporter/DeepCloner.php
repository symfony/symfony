<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarExporter;

use Symfony\Component\VarExporter\Exception\ClassNotFoundException;
use Symfony\Component\VarExporter\Exception\LogicException;
use Symfony\Component\VarExporter\Exception\NotInstantiableTypeException;

/**
 * Deep-clones PHP values while preserving copy-on-write benefits for strings and arrays.
 *
 * Unlike unserialize(serialize()), this approach does not reallocate strings and scalar-only
 * arrays, allowing PHP's copy-on-write mechanism to share memory for these values.
 *
 * DeepCloner instances are serializable: the serialized form is a pure array; it contains
 * only scalars and nested arrays, no objects. This makes it suitable for encoding with
 * json_encode(), var_export(), or any serializer that handles plain PHP arrays.
 * The format uses a compact representation that deduplicates class and property names,
 * typically producing a payload smaller than serialize($value) itself.
 *
 * The heavy lifting is delegated to deepclone_to_array() / deepclone_from_array(), which
 * come from either the native `deepclone` PHP extension (for a 4-5x speedup) or the
 * `symfony/polyfill-deepclone` package when the extension is not loaded.
 *
 * @template T
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class DeepCloner
{
    private array $payload;

    /**
     * @param T                 $value
     * @param list<string>|null $allowedClasses Classes that may be serialized.
     *                                          null (default) allows all classes. An empty array allows none.
     */
    public function __construct(mixed $value, ?array $allowedClasses = null)
    {
        try {
            $this->payload = deepclone_to_array($value, $allowedClasses);
        } catch (\DeepClone\NotInstantiableException $e) {
            throw new NotInstantiableTypeException($e);
        }
    }

    /**
     * Deep-clones a PHP value.
     *
     * @template U
     *
     * @param U                 $value
     * @param list<string>|null $allowedClasses classes that may be serialized/deserialized
     *
     * @return U
     */
    public static function deepClone(mixed $value, ?array $allowedClasses = null): mixed
    {
        return (new self($value, $allowedClasses))->clone($allowedClasses);
    }

    /**
     * Returns true when the value doesn't need cloning (scalars, null, enums, scalar-only arrays).
     */
    public function isStaticValue(): bool
    {
        return \array_key_exists('value', $this->payload);
    }

    /**
     * Creates a deep clone of the value.
     *
     * @param list<string>|null $allowedClasses Classes that may be instantiated.
     *                                          null (default) allows all classes. An empty array allows none.
     *
     * @return T
     */
    public function clone(?array $allowedClasses = null): mixed
    {
        if (\array_key_exists('value', $this->payload)) {
            return $this->payload['value'];
        }

        try {
            return deepclone_from_array($this->payload, $allowedClasses);
        } catch (\DeepClone\ClassNotFoundException $e) {
            throw new ClassNotFoundException($e);
        } catch (\DeepClone\NotInstantiableException $e) {
            throw new NotInstantiableTypeException($e);
        }
    }

    /**
     * Creates a deep clone of the root object using a different class.
     *
     * The target class must be compatible with the original (typically in the same hierarchy).
     *
     * @template U of object
     *
     * @param class-string<U>   $class
     * @param list<string>|null $allowedClasses Classes that may be instantiated.
     *                                          null (default) allows all classes. An empty array allows none.
     *
     * @return U
     */
    public function cloneAs(string $class, ?array $allowedClasses = null): object
    {
        if (\array_key_exists('value', $this->payload) || !\is_int($this->payload['prepared'] ?? null) || $this->payload['prepared'] < 0) {
            throw new LogicException('DeepCloner::cloneAs() requires the value to be an object.');
        }

        $payload = $this->payload;
        $rootId = $payload['prepared'];

        // Add the new class to the dedup'd list and remember its index
        $classes = $payload['classes'];
        if (!\is_array($classes)) {
            $classes = '' !== $classes ? [$classes] : [];
        }
        $newCidx = \count($classes);
        $classes[] = $class;
        $payload['classes'] = $classes;

        // Expand objectMeta to its array form so we can address the root entry
        $meta = $payload['objectMeta'];
        if (\is_int($meta)) {
            $meta = $meta > 0 ? array_fill(0, $meta, 0) : [];
        }
        $entry = $meta[$rootId] ?? null;
        if (\is_array($entry)) {
            $meta[$rootId] = [$newCidx, $entry[1]];
        } else {
            $meta[$rootId] = $newCidx;
        }
        $payload['objectMeta'] = $meta;

        try {
            return deepclone_from_array($payload, $allowedClasses);
        } catch (\DeepClone\ClassNotFoundException $e) {
            throw new ClassNotFoundException($e);
        } catch (\DeepClone\NotInstantiableException $e) {
            throw new NotInstantiableTypeException($e);
        }
    }

    /**
     * Exports the cloner state as a pure array (no objects, only scalars and arrays).
     *
     * The returned array can be passed to {@see fromArray()} to restore the cloner.
     */
    public function toArray(): array
    {
        return $this->payload;
    }

    /**
     * Restores a DeepCloner from an array previously created by {@see toArray()}.
     *
     * @template U
     *
     * @return self<U>
     */
    public static function fromArray(array $data): self
    {
        $cloner = new self(null);
        $cloner->__unserialize($data);

        return $cloner;
    }

    public function __serialize(): array
    {
        return $this->payload;
    }

    public function __unserialize(array $data): void
    {
        // No upfront validation: deepclone_from_array() does it on first clone()
        // and throws \ValueError on malformed input. This avoids paying for the
        // validation twice on the happy path.
        $this->payload = $data;
    }
}
