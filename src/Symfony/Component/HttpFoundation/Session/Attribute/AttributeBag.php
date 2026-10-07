<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Session\Attribute;

/**
 * This class relates to session attribute storage.
 *
 * @implements \IteratorAggregate<string, mixed>
 */
class AttributeBag implements AttributeBagInterface, \IteratorAggregate, \Countable
{
    protected array $attributes = [];

    private string $name = 'attributes';
    private array $isolatedValues = [];
    private array $payloads = [];

    /**
     * @param string $storageKey The key used to store attributes in the session
     * @param bool   $isolate    Whether to deep-clone the values read from and passed to the bag, so that only values passed to set() are saved
     * @param bool   $debug      Whether to report changes saved without calling set() when $isolate is false
     */
    public function __construct(
        private string $storageKey = '_sf2_attributes',
        private bool $isolate = false,
        private bool $debug = false,
    ) {
        $this->debug = $debug && !$isolate;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function initialize(array &$attributes): void
    {
        $this->attributes = &$attributes;
        $this->isolatedValues = $this->payloads = [];
    }

    public function getStorageKey(): string
    {
        return $this->storageKey;
    }

    public function has(string $name): bool
    {
        return \array_key_exists($name, $this->attributes);
    }

    public function get(string $name, mixed $default = null): mixed
    {
        if (!\array_key_exists($name, $this->attributes)) {
            return $default;
        }

        if ($this->isolate) {
            return $this->getIsolatedValue($name);
        }

        if ($this->debug && !\array_key_exists($name, $this->payloads)) {
            $this->payloads[$name] = self::getPayload($this->attributes[$name]);
        }

        return $this->attributes[$name];
    }

    public function set(string $name, mixed $value): void
    {
        if ($this->isolate && (\is_array($value) || \is_object($value))) {
            $this->attributes[$name] = self::deepClone($value);
            $this->isolatedValues[$name] = $value;

            return;
        }

        $this->attributes[$name] = $value;
        unset($this->isolatedValues[$name]);

        if ($this->debug) {
            $this->payloads[$name] = self::getPayload($value);
        }
    }

    public function all(): array
    {
        if ($this->isolate) {
            $all = [];
            foreach ($this->attributes as $name => $value) {
                $all[$name] = $this->getIsolatedValue($name);
            }

            return $all;
        }

        if ($this->debug) {
            foreach ($this->attributes as $name => $value) {
                if (!\array_key_exists($name, $this->payloads)) {
                    $this->payloads[$name] = self::getPayload($value);
                }
            }
        }

        return $this->attributes;
    }

    public function replace(array $attributes): void
    {
        $this->attributes = [];
        $this->isolatedValues = $this->payloads = [];
        foreach ($attributes as $key => $value) {
            $this->set($key, $value);
        }
    }

    public function remove(string $name): mixed
    {
        $retval = null;
        if (\array_key_exists($name, $this->attributes)) {
            $retval = \array_key_exists($name, $this->isolatedValues) ? $this->isolatedValues[$name] : $this->attributes[$name];
            unset($this->attributes[$name], $this->isolatedValues[$name], $this->payloads[$name]);
        }

        return $retval;
    }

    public function clear(): mixed
    {
        $return = $this->isolatedValues ? array_replace($this->attributes, $this->isolatedValues) : $this->attributes;
        $this->attributes = [];
        $this->isolatedValues = $this->payloads = [];

        return $return;
    }

    /**
     * Returns an iterator for attributes.
     *
     * @return \ArrayIterator<string, mixed>
     */
    public function getIterator(): \ArrayIterator
    {
        return new
            /** @extends \ArrayIterator<string, mixed> */
            class($this->all()) extends \ArrayIterator {
                public function key(): string
                {
                    return (string) parent::key();
                }
            };
    }

    /**
     * Returns the number of attributes.
     */
    public function count(): int
    {
        return \count($this->attributes);
    }

    /**
     * Triggers a deprecation for each value changed since it was read or set without calling set() again.
     *
     * @internal
     */
    public function reportChangesWithoutSet(): void
    {
        foreach ($this->payloads as $name => $payload) {
            if (null === $payload || !\array_key_exists($name, $this->attributes)) {
                continue;
            }

            $current = self::getPayload($this->attributes[$name]);

            if ($current !== $payload && serialize($current) !== serialize($payload)) {
                $this->payloads[$name] = $current;
                trigger_deprecation('symfony/http-foundation', '8.2', 'Saving changes made to the value of session attribute "%s" without calling "set()" afterwards is deprecated; call "set()" with the changed value instead.', $name);
            }
        }
    }

    private function getIsolatedValue(string $name): mixed
    {
        if (\array_key_exists($name, $this->isolatedValues)) {
            return $this->isolatedValues[$name];
        }

        $value = $this->attributes[$name];

        if (\is_array($value) || \is_object($value)) {
            $value = $this->isolatedValues[$name] = self::deepClone($value);
        }

        return $value;
    }

    private static function deepClone(array|object $value): array|object
    {
        $payload = deepclone_to_array($value);

        return \array_key_exists('value', $payload) ? $payload['value'] : deepclone_from_array($payload);
    }

    /**
     * Returns the deepclone payload of values that hold objects, null otherwise.
     */
    private static function getPayload(mixed $value): ?array
    {
        if (!\is_array($value) && !\is_object($value)) {
            return null;
        }

        try {
            $payload = deepclone_to_array($value);
        } catch (\DeepClone\NotInstantiableException) {
            return null;
        }

        return \array_key_exists('value', $payload) ? null : $payload;
    }
}
