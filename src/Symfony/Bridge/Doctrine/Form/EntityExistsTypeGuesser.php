<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Form;

use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\MappingException;
use Symfony\Bridge\Doctrine\Form\ChoiceList\EntityFieldValueChoiceLoader;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Bridge\Doctrine\Validator\Constraints\EntityExists;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormTypeGuesserInterface;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\TypeGuess;
use Symfony\Component\Form\Guess\ValueGuess;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyWriteInfo;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Mapping\ClassMetadataInterface;
use Symfony\Component\Validator\Mapping\Factory\MetadataFactoryInterface;

if (!interface_exists(FormTypeGuesserInterface::class)) {
    throw new \LogicException('You cannot use the "Symfony\Bridge\Doctrine\Form\EntityExistsTypeGuesser" class as the "symfony/form" package is not installed. Try running "composer require symfony/form".');
}

/**
 * Guesses a choice of the distinct values of the field that {@see EntityExists} looks a property up by.
 *
 * Register this guesser explicitly only for applications where the eligible fields have bounded, non-sensitive values: rendering or submitting a non-empty value loads all distinct choices. Submitted values must belong to the repository's query scope, independently of EntityExists validation groups.
 *
 * Only lookups by "identifierField" are guessed: EntityType covers the identifiers. Only properties accepting null and the PHP type of the field can hold the choices.
 */
final class EntityExistsTypeGuesser implements FormTypeGuesserInterface
{
    /**
     * PHP types of columns whose distinct values can be represented by ChoiceType.
     *
     * Array columns would become choice groups; text LOBs cannot be compared by DISTINCT on Oracle or Db2. The bridge's UID types are resolved separately.
     */
    private const PHP_TYPES = [
        'boolean' => 'bool',
        'integer' => 'int',
        'smallint' => 'int',
        'bigint' => 'int',
        'float' => 'float',
        'decimal' => 'string',
        'string' => 'string',
        'ascii_string' => 'string',
        'guid' => 'string',
        'date' => \DateTime::class,
        'datetime' => \DateTime::class,
        'datetimetz' => \DateTime::class,
        'time' => \DateTime::class,
        'date_immutable' => \DateTimeImmutable::class,
        'datetime_immutable' => \DateTimeImmutable::class,
        'datetimetz_immutable' => \DateTimeImmutable::class,
        'time_immutable' => \DateTimeImmutable::class,
        'dateinterval' => \DateInterval::class,
    ];

    /**
     * The column types an enum can be mapped to, as a single value.
     */
    private const ENUM_COLUMN_TYPES = ['string', 'ascii_string', 'integer', 'smallint'];

    private ReflectionExtractor $writeInfoExtractor;

    public function __construct(
        private ManagerRegistry $registry,
        private MetadataFactoryInterface $metadataFactory,
    ) {
        // the same extractor as PropertyAccessor, so that the guess follows what it will write to
        $this->writeInfoExtractor = new ReflectionExtractor(['set'], null, null, false);
    }

    public function guessType(string $class, string $property): ?TypeGuess
    {
        if (!$constraint = $this->getConstraint($class, $property)) {
            return null;
        }

        try {
            $loader = $this->createLoader($constraint, $this->getWriteTargetType($class, $property));
        } catch (\InvalidArgumentException|\ReflectionException|MappingException|DBALException) {
            return null;
        }

        if (null === $loader) {
            return null;
        }

        return new TypeGuess(ChoiceType::class, [
            'choice_loader' => $loader,
            'choice_value' => $loader->getValue(...),
            'choice_label' => $loader->getLabel(...),
            'choice_translation_domain' => false,
            'placeholder' => '',
        ], Guess::VERY_HIGH_CONFIDENCE);
    }

    public function guessRequired(string $class, string $property): ?ValueGuess
    {
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

    private function getConstraint(string $class, string $property): ?EntityExists
    {
        $classMetadata = $this->metadataFactory->getMetadataFor($class);

        if (!$classMetadata instanceof ClassMetadataInterface || !$classMetadata->hasPropertyMetadata($property)) {
            return null;
        }

        $found = null;
        foreach ($classMetadata->getPropertyMetadata($property) as $memberMetadata) {
            foreach ($memberMetadata->getConstraints() as $constraint) {
                if (!$constraint instanceof EntityExists) {
                    continue;
                }

                // the constraint is repeatable, a single list of choices cannot satisfy several lookups
                if ($found) {
                    return null;
                }

                $found = $constraint;
            }
        }

        // EntityExists refuses an "identifierField" with a "repositoryMethod", whose results cannot be listed
        return $found?->identifierField ? $found : null;
    }

    /**
     * @param \ReflectionType|false|null $writeTargetType false when nothing can be written to
     */
    private function createLoader(EntityExists $constraint, \ReflectionType|false|null $writeTargetType): ?EntityFieldValueChoiceLoader
    {
        // the empty choice is written as null
        if (false === $writeTargetType || ($writeTargetType && !$writeTargetType->allowsNull())) {
            return null;
        }

        // resolved as EntityExistsValidator does
        $manager = $constraint->em ? $this->registry->getManager($constraint->em) : $this->registry->getManagerForClass($constraint->entityClass);

        if (!$manager instanceof EntityManagerInterface) {
            return null;
        }

        $classMetadata = $manager->getClassMetadata($constraint->entityClass);

        // associations hold entities, not values
        if (!$classMetadata->hasField($field = $constraint->identifierField) || $classMetadata->isIdentifier($field)) {
            return null;
        }

        $typeName = $classMetadata->getTypeOfField($field);
        $config = $manager->getConnection()->getConfiguration();
        $type = method_exists($config, 'getTypeProvider') ? $config->getTypeProvider()->get($typeName) : Type::getType($typeName);
        if ($enumClass = $classMetadata->getFieldMapping($field)->enumType) {
            // an enum stored in an array column holds several values
            $phpType = \in_array($typeName, self::ENUM_COLUMN_TYPES, true) ? $enumClass : null;
        } else {
            $phpType = match (true) {
                // only the uid types of the bridge are known to hold uids
                $type instanceof UuidType => Uuid::class,
                $type instanceof UlidType => Ulid::class,
                default => self::PHP_TYPES[$typeName] ?? null,
            };
        }

        // values the property cannot hold would fail to be written
        if (null === $phpType || !self::accepts($writeTargetType, $phpType)) {
            return null;
        }

        return new EntityFieldValueChoiceLoader($manager, $constraint->entityClass, $field, $type, $phpType, $enumClass);
    }

    /**
     * Resolves the type of the target that PropertyAccessor writes a value of the property to.
     *
     * @return \ReflectionType|false|null The type, null when the target is not typed, false when there is no public target
     */
    private function getWriteTargetType(string $class, string $property): \ReflectionType|false|null
    {
        $writeInfo = $this->writeInfoExtractor->getWriteInfo($class, $property, [
            'enable_getter_setter_extraction' => true,
            'enable_constructor_extraction' => false,
            'enable_adder_remover_extraction' => false,
        ]);

        // the extractor only reports public targets
        if (null === $writeInfo || PropertyWriteInfo::TYPE_NONE === $writeInfo->getType()) {
            return false;
        }

        $target = match ($writeInfo->getType()) {
            PropertyWriteInfo::TYPE_METHOD => (new \ReflectionMethod($class, $writeInfo->getName()))->getParameters()[0] ?? null,
            PropertyWriteInfo::TYPE_PROPERTY => new \ReflectionProperty($class, $writeInfo->getName()),
            default => null,
        };

        if (null === $target) {
            return false;
        }

        if ($target instanceof \ReflectionProperty) {
            $target = $target->getHook(\PropertyHookType::Set)?->getParameters()[0] ?? $target;
        }

        return $target->getType();
    }

    /**
     * Tells whether a target of the given type holds values of the given PHP type as they are, without coercion.
     */
    private static function accepts(?\ReflectionType $type, string $phpType): bool
    {
        if (null === $type) {
            return true;
        }

        foreach ($type instanceof \ReflectionUnionType ? $type->getTypes() : [$type] as $namedType) {
            if (!$namedType instanceof \ReflectionNamedType) {
                continue;
            }

            $name = $namedType->getName();

            if ($name === $phpType || 'mixed' === $name) {
                return true;
            }

            if (class_exists($phpType) && ('object' === $name || (!$namedType->isBuiltin() && is_a($phpType, $name, true)))) {
                return true;
            }
        }

        return false;
    }
}
