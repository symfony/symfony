<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Part;

use Symfony\Component\Mime\Header\Headers;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class AbstractPart
{
    private Headers $headers;

    public function __construct()
    {
        $this->headers = new Headers();
    }

    public function getHeaders(): Headers
    {
        return $this->headers;
    }

    public function getPreparedHeaders(): Headers
    {
        $headers = clone $this->headers;
        $headers->setHeaderBody('Parameterized', 'Content-Type', $this->getMediaType().'/'.$this->getMediaSubtype());

        return $headers;
    }

    public function toString(): string
    {
        return $this->getPreparedHeaders()->toString()."\r\n".$this->bodyToString();
    }

    public function toIterable(): iterable
    {
        yield $this->getPreparedHeaders()->toString();
        yield "\r\n";
        yield from $this->bodyToIterable();
    }

    public function asDebugString(): string
    {
        return $this->getMediaType().'/'.$this->getMediaSubtype();
    }

    abstract public function bodyToString(): string;

    abstract public function bodyToIterable(): iterable;

    abstract public function getMediaType(): string;

    abstract public function getMediaSubtype(): string;

    public function __serialize(): array
    {
        if (!method_exists($this, '__sleep')) {
            return ['headers' => $this->headers] + $this->getSubclassState();
        }

        trigger_deprecation('symfony/mime', '7.4', 'Implementing "%s::__sleep()" is deprecated, use "__serialize()" instead.', get_debug_type($this));

        $data = [];
        foreach ($this->__sleep() as $key) {
            try {
                if (($r = new \ReflectionProperty($this, $key))->isInitialized($this)) {
                    $data[$key] = $r->getValue($this);
                }
            } catch (\ReflectionException) {
                $data[$key] = $this->$key;
            }
        }

        return $data;
    }

    public function __unserialize(array $data): void
    {
        if ($wakeup = method_exists($this, '__wakeup') && \in_array((new \ReflectionMethod($this, '__unserialize'))->class, [self::class, AbstractMultipartPart::class], true)) {
            trigger_deprecation('symfony/mime', '7.4', 'Implementing "%s::__wakeup()" is deprecated, use "__unserialize()" instead.', get_debug_type($this));
        }

        if (['headers'] === array_keys($data)) {
            $this->headers = $data['headers'];

            if ($wakeup) {
                $this->__wakeup();
            }

            return;
        }

        // the payload comes from __serialize() for a subclass that declares properties, or from the native serialization of Symfony < 7.4
        $headersKey = \array_key_exists('headers', $data) ? 'headers' : "\0".self::class."\0headers";
        if (\array_key_exists($headersKey, $data) && self::isCoreClass((new \ReflectionMethod($this, '__unserialize'))->class) && self::isCoreClass((new \ReflectionMethod($this, '__serialize'))->class)) {
            $this->headers = $data[$headersKey];
            unset($data[$headersKey]);
            $this->setSubclassState($data);

            if ($wakeup) {
                $this->__wakeup();
            }

            return;
        }

        trigger_deprecation('symfony/mime', '7.4', 'Passing more than just key "headers" to "%s::__unserialize()" is deprecated, populate properties in "%s::__unserialize()" instead.', self::class, get_debug_type($this));

        self::rejectStringCasts(static::class, $data);

        \Closure::bind(function ($data) use ($wakeup) {
            foreach ($data as $key => $value) {
                $this->{("\0" === $key[0] ?? '') ? substr($key, 1 + strrpos($key, "\0")) : $key} = $value;
            }

            if ($wakeup) {
                $this->__wakeup();
            }
        }, $this, static::class)($data);
    }

    /**
     * Returns the properties declared by subclasses that are not part of Symfony, keyed like the native serialization does.
     */
    private function getSubclassState(): array
    {
        if (self::isCoreClass(static::class)) {
            return [];
        }

        $state = [];
        foreach ((array) $this as $key => $value) {
            if (!str_starts_with($key, "\0".__NAMESPACE__.'\\')) {
                $state[$key] = $value;
            }
        }

        return $state;
    }

    private function setSubclassState(array $data): void
    {
        foreach ($data as $key => $value) {
            [$class, $name] = "\0" === ($key[0] ?? '') ? explode("\0", substr($key, 1), 2) + [1 => ''] : ['*', $key];
            $class = '*' === $class ? static::class : $class;

            if (!$this instanceof $class) {
                throw new \BadMethodCallException('Cannot unserialize '.static::class);
            }

            $property = property_exists($class, $name) ? new \ReflectionProperty($class, $name) : null;

            if (self::isCoreClass($property?->class ?? $class) || ($property && $value instanceof \Stringable && self::castsToString($property, $value))) {
                throw new \BadMethodCallException('Cannot unserialize '.static::class);
            }

            \Closure::bind(function () use ($name, $value) {
                $this->$name = $value;
            }, $this, $class)();
        }
    }

    /**
     * Rejects the \Stringable values that would be cast to string when assigned by name in the scope of the class.
     */
    private static function rejectStringCasts(string $class, array $data): void
    {
        foreach ($data as $key => $value) {
            $name = "\0" === ($key[0] ?? '') ? substr($key, 1 + strrpos($key, "\0")) : $key;

            if ($value instanceof \Stringable && property_exists($class, $name) && self::castsToString(new \ReflectionProperty($class, $name), $value)) {
                throw new \BadMethodCallException('Cannot unserialize '.$class);
            }
        }
    }

    private static function isCoreClass(string $class): bool
    {
        return str_starts_with($class, __NAMESPACE__.'\\');
    }

    private static function castsToString(\ReflectionProperty $property, object $value): bool
    {
        $type = $property->getType();
        $string = false;

        foreach ($type instanceof \ReflectionUnionType ? $type->getTypes() : [$type] as $t) {
            if (!$t instanceof \ReflectionNamedType) {
                continue;
            }

            if ('string' === $t->getName()) {
                $string = true;
            } elseif ('object' === $t->getName() || (!$t->isBuiltin() && $value instanceof ($t->getName()))) {
                return false;
            }
        }

        return $string;
    }
}
