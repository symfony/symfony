<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Util;

/**
 * Finds the members that a method, a parameter or a property inherits its PHPDoc from, like PHPStan does.
 *
 * @internal
 */
final class PhpDocInheritance
{
    /**
     * Yields the members that a method, a parameter or a property overrides or implements, nearest first.
     *
     * The parent class comes first, then the interfaces in the order of their declaration, then the abstract methods of the traits, each one followed by the members that it overrides itself. Parameters are matched by position, except the ones of constructors, which are matched by name, like promoted properties. Each member comes with the class where its docblock is written and the class that "self" refers to in it.
     *
     * @return iterable<array{\ReflectionMethod|\ReflectionParameter|\ReflectionProperty, \ReflectionClass, string}>
     */
    public static function getOverriddenMembers(\ReflectionMethod|\ReflectionParameter|\ReflectionProperty $member, ?string $selfClass = null): iterable
    {
        if ($member instanceof \ReflectionProperty && $member->isPromoted() && !$member = self::getConstructorParameter($member->getDeclaringClass()->getConstructor(), $member->name)) {
            return;
        }

        if ($member instanceof \ReflectionParameter) {
            if (!($method = $member->getDeclaringFunction()) instanceof \ReflectionMethod) {
                return;
            }

            foreach (self::getOverriddenMembers($method) as [$source, $docClass, $sourceSelfClass]) {
                if ($parameter = '__construct' === $method->name ? self::getConstructorParameter($source, $member->name) : $source->getParameters()[$member->getPosition()] ?? null) {
                    yield [$parameter, $docClass, $sourceSelfClass];
                }
            }

            return;
        }

        $class = $member->getDeclaringClass();
        $sources = [];

        if (($parent = $class->getParentClass()) && ($source = self::getMember($parent, $member)) && !$source->isPrivate()) {
            $sources[] = [$source, $source->class];
        }

        // the interfaces of the parent class and of other interfaces are reached from there
        $interfaces = $class->getInterfaceNames();
        if ($parent) {
            $interfaces = array_diff($interfaces, $parent->getInterfaceNames());
        }
        foreach ($interfaces as $interface) {
            $interfaces = array_diff($interfaces, class_implements($interface));
        }
        foreach ($interfaces as $interface) {
            if ($source = self::getMember(new \ReflectionClass($interface), $member)) {
                $sources[] = [$source, $source->class];
            }
        }

        if ($member instanceof \ReflectionMethod) {
            foreach ($class->getTraits() as $trait) {
                if (($source = self::getMember($trait, $member)) && $source->isAbstract()) {
                    $sources[] = [$source, $selfClass ?? $class->name];
                }
            }
        }

        foreach ($sources as [$source, $sourceSelfClass]) {
            $docClass = self::getDeclaringTrait($source) ?? $source->getDeclaringClass();

            yield [$source, $docClass, $sourceSelfClass];

            // the constructor that promotes a property documents it too
            if ($source instanceof \ReflectionProperty && $source->isPromoted() && ($constructor = $source->getDeclaringClass()->getConstructor()) && $parameter = self::getConstructorParameter($constructor, $source->name)) {
                yield [$parameter, self::getDeclaringTrait($constructor) ?? $constructor->getDeclaringClass(), $sourceSelfClass];
            }

            yield from self::getOverriddenMembers($source, $sourceSelfClass);
        }
    }

    /**
     * Tells whether a member has the same native type as the member that it inherits a PHPDoc type from, or whether that member has no native type.
     */
    public static function hasSameNativeType(\ReflectionMethod|\ReflectionParameter|\ReflectionProperty $member, \ReflectionMethod|\ReflectionParameter|\ReflectionProperty $source, string $sourceSelfClass): bool
    {
        if (!$sourceType = $source instanceof \ReflectionMethod ? $source->getReturnType() : $source->getType()) {
            return true;
        }

        if (!$type = $member instanceof \ReflectionMethod ? $member->getReturnType() : $member->getType()) {
            return false;
        }

        return self::resolveNativeType($type, $member->getDeclaringClass()->name) === self::resolveNativeType($sourceType, $sourceSelfClass);
    }

    public static function getConstructorParameter(?\ReflectionMethod $constructor, string $name): ?\ReflectionParameter
    {
        return array_find($constructor?->getParameters() ?? [], static fn (\ReflectionParameter $parameter): bool => $name === $parameter->name);
    }

    /**
     * Finds the trait where a method or a property is written, since reflection gives the class using the trait as their declaring class.
     */
    public static function getDeclaringTrait(\ReflectionMethod|\ReflectionProperty $member, ?\ReflectionClass $class = null): ?\ReflectionClass
    {
        foreach (($class ?? $member->getDeclaringClass())->getTraits() as $trait) {
            if ($declaringTrait = self::getDeclaringTrait($member, $trait)) {
                return $declaringTrait;
            }

            if ($member instanceof \ReflectionProperty) {
                // a class can declare a property of its traits again, with another doc comment
                if ($trait->hasProperty($member->name) && $trait->getProperty($member->name)->getDocComment() === $member->getDocComment()) {
                    return $trait;
                }
            } elseif ($trait->getFileName() === $member->getFileName() && $trait->getStartLine() <= $member->getStartLine() && $member->getEndLine() <= $trait->getEndLine()) {
                return $trait;
            }
        }

        return null;
    }

    private static function getMember(\ReflectionClass $class, \ReflectionMethod|\ReflectionProperty $member): \ReflectionMethod|\ReflectionProperty|null
    {
        if ($member instanceof \ReflectionMethod) {
            return $class->hasMethod($member->name) ? $class->getMethod($member->name) : null;
        }

        return $class->hasProperty($member->name) ? $class->getProperty($member->name) : null;
    }

    /**
     * Replaces "self" and "parent" with the classes they refer to, as PHP does since 8.5 for the members of classes, but not for the members of traits.
     */
    private static function resolveNativeType(\ReflectionType $type, string $selfClass): string
    {
        return preg_replace_callback('/(?<![\w\\\\])(?:self|parent)(?![\w\\\\])/i', static fn (array $m): string => 'self' === strtolower($m[0]) ? $selfClass : (get_parent_class($selfClass) ?: $m[0]), (string) $type);
    }
}
