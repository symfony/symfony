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

use Symfony\Component\Clock\DatePoint;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateIntervalType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\TypeGuess;
use Symfony\Component\Form\Guess\ValueGuess;
use Symfony\Component\TypeInfo\Exception\ExceptionInterface;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\NullableType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\TypeIdentifier;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolverInterface;

/**
 * Guesses the form type of a field from the declared type of the property it is named after.
 */
final class TypeInfoFormTypeGuesser implements FormTypeGuesserInterface
{
    /**
     * @var array<string, array<string, Type|false>>
     */
    private array $cache = [];

    public function __construct(
        private ?TypeResolverInterface $typeResolver = null,
    ) {
    }

    public function guessType(string $class, string $property): ?TypeGuess
    {
        if (!$type = $this->getPropertyType($class, $property)) {
            return null;
        }

        if ($type instanceof NullableType) {
            $type = $type->getWrappedType();
        }

        if ($type instanceof BuiltinType) {
            return match ($type->getTypeIdentifier()) {
                TypeIdentifier::INT => new TypeGuess(IntegerType::class, [], Guess::MEDIUM_CONFIDENCE),
                TypeIdentifier::FLOAT => new TypeGuess(NumberType::class, [], Guess::MEDIUM_CONFIDENCE),
                TypeIdentifier::BOOL => new TypeGuess(CheckboxType::class, [], Guess::MEDIUM_CONFIDENCE),
                default => null,
            };
        }

        if (!$type instanceof ObjectType) {
            return null;
        }

        // the form types create instances of these exact classes, which a property typed with a subclass would reject
        return match ($type->getClassName()) {
            \DateTime::class => new TypeGuess(DateTimeType::class, ['input' => 'datetime'], Guess::MEDIUM_CONFIDENCE),
            \DateTimeImmutable::class, \DateTimeInterface::class => new TypeGuess(DateTimeType::class, ['input' => 'datetime_immutable'], Guess::MEDIUM_CONFIDENCE),
            DatePoint::class => new TypeGuess(DateTimeType::class, ['input' => 'date_point'], Guess::MEDIUM_CONFIDENCE),
            \DateInterval::class => new TypeGuess(DateIntervalType::class, [], Guess::MEDIUM_CONFIDENCE),
            default => null,
        };
    }

    public function guessRequired(string $class, string $property): ?ValueGuess
    {
        if (!$type = $this->getPropertyType($class, $property)) {
            return null;
        }

        // a required checkbox must be checked, while a bool property accepts false too
        if ($type->isNullable() || ($type instanceof BuiltinType && TypeIdentifier::BOOL === $type->getTypeIdentifier())) {
            return new ValueGuess(false, Guess::LOW_CONFIDENCE);
        }

        return null;
    }

    public function guessMaxLength(string $class, string $property): ?ValueGuess
    {
        return null;
    }

    public function guessPattern(string $class, string $property): ?ValueGuess
    {
        return null;
    }

    private function getPropertyType(string $class, string $property): Type|false
    {
        if (isset($this->cache[$class][$property])) {
            return $this->cache[$class][$property];
        }

        try {
            $type = ($this->typeResolver ??= TypeResolver::create())->resolve(new \ReflectionProperty($class, $property));
        } catch (\ReflectionException|ExceptionInterface) {
            return $this->cache[$class][$property] = false;
        }

        return $this->cache[$class][$property] = $type->isIdentifiedBy(TypeIdentifier::MIXED) ? false : $type;
    }
}
