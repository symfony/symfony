<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TypeInfo\TypeContext;

use Symfony\Component\TypeInfo\Exception\LogicException;
use Symfony\Component\TypeInfo\Type;

/**
 * Type resolving context.
 *
 * Helps to retrieve declaring class, called class, parent class, templates
 * and normalize classes according to the current namespace and uses.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 */
final class TypeContext
{
    /**
     * @var array<string, bool>
     */
    private static array $classExistCache = [];

    /**
     * @param array<string, string> $uses
     * @param array<string, Type>   $templates
     * @param array<string, Type>   $typeAliases
     */
    public function __construct(
        public readonly string $calledClassName,
        public readonly string $declaringClassName,
        public readonly ?string $namespace = null,
        public readonly array $uses = [],
        public readonly array $templates = [],
        public readonly array $typeAliases = [],
    ) {
    }

    /**
     * Normalize class name according to current namespace and uses.
     */
    public function normalize(string $name): string
    {
        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }

        $nameParts = explode('\\', $name);
        $firstNamePart = $nameParts[0];
        if (isset($this->uses[$firstNamePart])) {
            if (1 === \count($nameParts)) {
                return $this->uses[$firstNamePart];
            }
            array_shift($nameParts);

            return \sprintf('%s\\%s', $this->uses[$firstNamePart], implode('\\', $nameParts));
        }

        if (null !== $this->namespace) {
            return \sprintf('%s\\%s', $this->namespace, $name);
        }

        return $name;
    }

    /**
     * @return class-string
     */
    public function getDeclaringClass(): string
    {
        return $this->normalize($this->declaringClassName);
    }

    /**
     * @return class-string
     */
    public function getCalledClass(): string
    {
        return $this->normalize($this->calledClassName);
    }

    /**
     * @return class-string
     */
    public function getParentClass(): string
    {
        $declaringClassName = $this->getDeclaringClass();

        if (false === $parentClass = get_parent_class($declaringClassName)) {
            throw new LogicException(\sprintf('"%s" do not extend any class.', $declaringClassName));
        }

        if (!isset(self::$classExistCache[$parentClass])) {
            self::$classExistCache[$parentClass] = false;

            if (class_exists($parentClass)) {
                self::$classExistCache[$parentClass] = true;
            } else {
                try {
                    new \ReflectionClass($parentClass);
                    self::$classExistCache[$parentClass] = true;
                } catch (\Throwable) {
                }
            }
        }

        return self::$classExistCache[$parentClass] ? $parentClass : $this->normalize(str_replace($this->namespace.'\\', '', $parentClass));
    }
}
