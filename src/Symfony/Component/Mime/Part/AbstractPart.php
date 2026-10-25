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
        return ['headers' => $this->headers] + $this->getSubclassState();
    }

    public function __unserialize(array $data): void
    {
        $headersKey = \array_key_exists('headers', $data) ? 'headers' : "\0".self::class."\0headers";
        $this->headers = $data[$headersKey];
        unset($data[$headersKey]);

        // the payload comes from __serialize() for a subclass that declares properties, or from the native serialization of Symfony < 7.4
        if ($data && self::isCoreClass((new \ReflectionMethod($this, '__unserialize'))->class) && self::isCoreClass((new \ReflectionMethod($this, '__serialize'))->class)) {
            $this->setSubclassState($data);
        }
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
