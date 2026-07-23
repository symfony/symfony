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
 * Security:
 * A DeepCloner instance is itself safe to serialize and unserialize (its serialized form is
 * a pure array of scalars and nested arrays, no objects), so it can be round-tripped via
 * unserialize($data, ['allowed_classes' => [DeepCloner::class]]) without instantiating any
 * other class at unserialize() time. However, the $allowedClasses discipline that applies
 * to PHP's native unserialize() extends to clone(), cloneAs(), deepClone() and to the cloner
 * returned by fromArray(): these are what actually instantiate the inner objects (running
 * __wakeup() / __unserialize() on them). $allowedClasses = null (the default) lets the cloner
 * instantiate any class loaded in the process, including classes with side effects in
 * __wakeup() / __unserialize(), which reproduces the full unserialize-gadget surface.
 * Callers that obtain a DeepCloner (or its array payload) from an untrusted source MUST pass
 * an explicit allow-list to the clone methods; pass [] to forbid all classes. Allow-list
 * violations surface as \ValueError (matching PHP's unserialize() contract), not as
 * {@see Exception\ExceptionInterface}; callers that want to catch every DeepCloner failure
 * must catch both.
 *
 * @template T
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class DeepCloner
{
    private array $payload;

    /**
     * @param T                       $value
     * @param list<class-string>|null $allowedClasses    Classes that may be instantiated; null (default) allows all; an empty array allows none
     * @param bool                    $allowNamedClosure Whether to allow cloning of named closures, enable only if you trust the payload
     *
     * @throws NotInstantiableTypeException When $value (or a nested value) cannot be serialized
     * @throws \ValueError                  When a class in the graph is not in $allowedClasses
     */
    public function __construct(mixed $value, ?array $allowedClasses = null, bool $allowNamedClosure = false)
    {
        try {
            $this->payload = deepclone_to_array($value, $allowedClasses, $allowNamedClosure);
        } catch (\DeepClone\NotInstantiableException $e) {
            throw new NotInstantiableTypeException($e);
        }
    }

    /**
     * Deep-clones a PHP value.
     *
     * When the input may come from an untrusted source, pass an explicit allow-list:
     * $allowedClasses = null (default) allows every loaded class to be instantiated,
     * which runs __wakeup() / __unserialize() on them. Pass [] to forbid all classes.
     *
     * @template U
     *
     * @param U                       $value
     * @param list<class-string>|null $allowedClasses    Classes that may be instantiated; null (default) allows all; an empty array allows none
     * @param bool                    $allowNamedClosure Whether to allow cloning of named closures, enable only if you trust the payload
     *
     * @return U
     *
     * @throws ClassNotFoundException       When a class in the graph cannot be loaded
     * @throws NotInstantiableTypeException When $value (or a nested value) cannot be serialized/instantiated
     * @throws \ValueError                  When a class in the graph is not in $allowedClasses
     */
    public static function deepClone(mixed $value, ?array $allowedClasses = null, bool $allowNamedClosure = false): mixed
    {
        return (new self($value, $allowedClasses, $allowNamedClosure))->clone($allowedClasses, $allowNamedClosure);
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
     * When this DeepCloner was obtained from an untrusted source (e.g. unserialize() of
     * attacker-controlled data, or fromArray() on an attacker-controlled payload), the
     * $allowedClasses argument is the security boundary: $allowedClasses = null (default)
     * lets any loaded class be instantiated and runs __wakeup() / __unserialize() on it,
     * reproducing the unserialize-gadget surface. Pass an explicit list (or [] for none).
     *
     * @param list<class-string>|null $allowedClasses    Classes that may be instantiated; null (default) allows all; an empty array allows none
     * @param bool                    $allowNamedClosure Whether to allow cloning of named closures, enable only if you trust the payload
     *
     * @return T
     *
     * @throws ClassNotFoundException       When a class in the graph cannot be loaded
     * @throws NotInstantiableTypeException When a class in the graph cannot be instantiated
     * @throws \ValueError                  When a class in the graph is not in $allowedClasses
     */
    public function clone(?array $allowedClasses = null, bool $allowNamedClosure = false): mixed
    {
        if (\array_key_exists('value', $this->payload)) {
            return $this->payload['value'];
        }

        try {
            return deepclone_from_array($this->payload, $allowedClasses, $allowNamedClosure);
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
     * When this DeepCloner was obtained from an untrusted source, the $allowedClasses argument
     * is the security boundary: $allowedClasses = null (default) lets any loaded class be
     * instantiated and runs __wakeup() / __unserialize() on it. Pass an explicit list (or []
     * for none). $class itself is not implicitly allow-listed; include it in $allowedClasses
     * when restricting.
     *
     * @template U of object
     *
     * @param class-string<U>         $class
     * @param list<class-string>|null $allowedClasses    Classes that may be instantiated; null (default) allows all; an empty array allows none
     * @param bool                    $allowNamedClosure Whether to allow cloning of named closures, enable only if you trust the payload
     *
     * @return U
     *
     * @throws LogicException               When the cloned value is not an object
     * @throws ClassNotFoundException       When $class or another class in the graph cannot be loaded
     * @throws NotInstantiableTypeException When $class or another class in the graph cannot be instantiated
     * @throws \ValueError                  When a class in the graph is not in $allowedClasses
     */
    public function cloneAs(string $class, ?array $allowedClasses = null, bool $allowNamedClosure = false): object
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
            return deepclone_from_array($payload, $allowedClasses, $allowNamedClosure);
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
     * The payload is stored as-is and validated lazily on the first clone() / cloneAs() call.
     * When $data comes from an untrusted source, the returned cloner must be used with an
     * explicit $allowedClasses allow-list on clone() / cloneAs(); otherwise any loaded class
     * referenced in the payload can be instantiated, running its __wakeup() / __unserialize().
     *
     * @return self<mixed>
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
