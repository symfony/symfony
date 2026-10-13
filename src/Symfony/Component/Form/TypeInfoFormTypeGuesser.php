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

use Psr\Cache\CacheItemPoolInterface;
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
     * @var array<string, array<string, array{?class-string, array<string, string>, ?bool}>>
     */
    private array $guesses = [];

    public function __construct(
        private ?TypeResolverInterface $typeResolver = null,
        private ?CacheItemPoolInterface $cache = null,
    ) {
    }

    public function guessType(string $class, string $property): ?TypeGuess
    {
        [$type, $options] = $this->getGuesses($class, $property);

        return null !== $type ? new TypeGuess($type, $options, Guess::MEDIUM_CONFIDENCE) : null;
    }

    public function guessRequired(string $class, string $property): ?ValueGuess
    {
        $required = $this->getGuesses($class, $property)[2];

        return null !== $required ? new ValueGuess($required, Guess::LOW_CONFIDENCE) : null;
    }

    public function guessMaxLength(string $class, string $property): ?ValueGuess
    {
        return null;
    }

    public function guessPattern(string $class, string $property): ?ValueGuess
    {
        return null;
    }

    /**
     * @return array{?class-string, array<string, string>, ?bool} The guessed form type, its options and the guessed value of the "required" option
     */
    private function getGuesses(string $class, string $property): array
    {
        if (isset($this->guesses[$class][$property])) {
            return $this->guesses[$class][$property];
        }

        if (!$this->cache) {
            return $this->guesses[$class][$property] = $this->computeGuesses($class, $property);
        }

        // rawurlencode() escapes the characters that PSR-6 keys do not allow
        $item = $this->cache->getItem(rawurlencode($class.'::'.$property));

        if (!$item->isHit()) {
            $this->cache->save($item->set($this->computeGuesses($class, $property)));
        }

        return $this->guesses[$class][$property] = $item->get();
    }

    /**
     * @return array{?class-string, array<string, string>, ?bool}
     */
    private function computeGuesses(string $class, string $property): array
    {
        try {
            $reflector = new \ReflectionClass($class);
            // a child class does not list the private properties of its parent classes
            while (!$reflector->hasProperty($property) && $reflector->getParentClass()) {
                $reflector = $reflector->getParentClass();
            }
            $type = ($this->typeResolver ??= TypeResolver::create())->resolve($reflector->getProperty($property));
        } catch (\ReflectionException|ExceptionInterface) {
            return [null, [], null];
        }

        if ($type->isIdentifiedBy(TypeIdentifier::MIXED)) {
            return [null, [], null];
        }

        // a required checkbox must be checked, while a bool property accepts false too
        $required = $type->isNullable() || ($type instanceof BuiltinType && TypeIdentifier::BOOL === $type->getTypeIdentifier()) ? false : null;

        if ($type instanceof NullableType) {
            $type = $type->getWrappedType();
        }

        if ($type instanceof BuiltinType) {
            return match ($type->getTypeIdentifier()) {
                TypeIdentifier::INT => [IntegerType::class, [], $required],
                TypeIdentifier::FLOAT => [NumberType::class, [], $required],
                TypeIdentifier::BOOL => [CheckboxType::class, [], $required],
                default => [null, [], $required],
            };
        }

        if (!$type instanceof ObjectType) {
            return [null, [], $required];
        }

        // the form types create instances of these exact classes, which a property typed with a subclass would reject
        return match ($type->getClassName()) {
            \DateTime::class => [DateTimeType::class, ['input' => 'datetime'], $required],
            \DateTimeImmutable::class, \DateTimeInterface::class => [DateTimeType::class, ['input' => 'datetime_immutable'], $required],
            DatePoint::class => [DateTimeType::class, ['input' => 'date_point'], $required],
            \DateInterval::class => [DateIntervalType::class, [], $required],
            default => [null, [], $required],
        };
    }
}
