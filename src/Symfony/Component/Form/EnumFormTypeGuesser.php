<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\TypeGuess;
use Symfony\Component\Form\Guess\ValueGuess;

final class EnumFormTypeGuesser implements FormTypeGuesserInterface
{
    /**
     * @var array<string, array<string, string|false>>
     */
    private array $cache = [];

    public function guessType(string $class, string $property): ?TypeGuess
    {
        if (!($enum = $this->getPropertyType($class, $property))) {
            return null;
        }

        return new TypeGuess(EnumType::class, ['class' => ltrim($enum, '?')], Guess::HIGH_CONFIDENCE);
    }

    public function guessRequired(string $class, string $property): ?ValueGuess
    {
        if (!($enum = $this->getPropertyType($class, $property))) {
            return null;
        }

        return new ValueGuess('?' !== $enum[0], Guess::HIGH_CONFIDENCE);
    }

    public function guessMaxLength(string $class, string $property): ?ValueGuess
    {
        return null;
    }

    public function guessPattern(string $class, string $property): ?ValueGuess
    {
        return null;
    }

    private function getPropertyType(string $class, string $property): string|false
    {
        if (isset($this->cache[$class][$property])) {
            return $this->cache[$class][$property];
        }

        try {
            $propertyReflection = new \ReflectionProperty($class, $property);
        } catch (\ReflectionException) {
            return $this->cache[$class][$property] = false;
        }

        $type = $propertyReflection->getType();
        if (!$type instanceof \ReflectionNamedType || !enum_exists($type->getName())) {
            $enum = false;
        } else {
            $enum = $type->getName();
            if ($type->allowsNull()) {
                $enum = '?'.$enum;
            }
        }

        return $this->cache[$class][$property] = $enum;
    }
}
