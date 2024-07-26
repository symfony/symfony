<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateIntervalType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use Symfony\Component\Form\Extension\Core\Type\UlidType;
use Symfony\Component\Form\Extension\Core\Type\UuidType;
use Symfony\Component\Form\FormTypeGuesserInterface;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\TypeGuess;
use Symfony\Component\Form\Guess\ValueGuess;

class ReflectionTypeGuesser implements FormTypeGuesserInterface
{
    public function guessType(string $class, string $property): ?TypeGuess
    {
        $type = $this->getReflectionType($class, $property);

        if (!($type instanceof \ReflectionNamedType)) {
            return null;
        }

        $name = $type->getName();

        if (enum_exists($name)) {
            return new TypeGuess(EnumType::class, ['class' => $name], Guess::MEDIUM_CONFIDENCE);
        }

        return match ($name) {
            // PHP types
            'bool' => new TypeGuess(CheckboxType::class, [], Guess::MEDIUM_CONFIDENCE),
            'float' => new TypeGuess(NumberType::class, [], Guess::MEDIUM_CONFIDENCE),
            'int' => new TypeGuess(IntegerType::class, [], Guess::MEDIUM_CONFIDENCE),
            'string' => new TypeGuess(TextType::class, [], Guess::LOW_CONFIDENCE),

            // PHP classes
            'DateTime' => new TypeGuess(DateTimeType::class, [], Guess::LOW_CONFIDENCE),
            'DateTimeImmutable' => new TypeGuess(DateTimeType::class, ['input' => 'datetime_immutable'], Guess::LOW_CONFIDENCE),
            'DateInterval' => new TypeGuess(DateIntervalType::class, [], Guess::MEDIUM_CONFIDENCE),
            'DateTimeZone' => new TypeGuess(TimezoneType::class, ['input' => 'datetimezone'], Guess::MEDIUM_CONFIDENCE),
            'IntlTimeZone' => new TypeGuess(TimezoneType::class, ['input' => 'intltimezone'], Guess::MEDIUM_CONFIDENCE),

            // Symfony classes
            'Symfony\Component\HttpFoundation\File\File' => new TypeGuess(FileType::class, [], Guess::MEDIUM_CONFIDENCE),
            'Symfony\Component\Uid\Ulid' => new TypeGuess(UlidType::class, [], Guess::MEDIUM_CONFIDENCE),
            'Symfony\Component\Uid\Uuid' => new TypeGuess(UuidType::class, [], Guess::MEDIUM_CONFIDENCE),

            default => null,
        };
    }

    public function guessRequired(string $class, string $property): ?ValueGuess
    {
        $type = $this->getReflectionType($class, $property);

        if (!$type) {
            return null;
        }

        if ($type instanceof \ReflectionNamedType && 'bool' === $type->getName()) {
            return new ValueGuess(false, Guess::MEDIUM_CONFIDENCE);
        }

        return new ValueGuess(!$type->allowsNull(), Guess::MEDIUM_CONFIDENCE);
    }

    public function guessMaxLength(string $class, string $property): ?ValueGuess
    {
        return null;
    }

    public function guessPattern(string $class, string $property): ?ValueGuess
    {
        return null;
    }

    private function getReflectionType(string $class, string $property): ?\ReflectionType
    {
        try {
            $reflection = new \ReflectionProperty($class, $property);
        } catch (\ReflectionException $e) {
            return null;
        }

        return $reflection->getType();
    }
}
