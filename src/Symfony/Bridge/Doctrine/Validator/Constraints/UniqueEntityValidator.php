<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Validator\Constraints;

use Doctrine\ORM\Mapping\ClassMetadata as OrmClassMetadata;
use Doctrine\ORM\Mapping\MappingException as ORMMappingException;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\MappingException as PersistenceMappingException;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Unique Entity Validator checks if one or a set of fields contain unique values.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class UniqueEntityValidator extends ConstraintValidator
{
    public function __construct(
        private readonly ManagerRegistry $registry,
    ) {
    }

    /**
     * @throws UnexpectedTypeException
     * @throws ConstraintDefinitionException
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueEntity) {
            throw new UnexpectedTypeException($constraint, UniqueEntity::class);
        }

        if (!\is_array($constraint->fields) && !\is_string($constraint->fields)) {
            throw new UnexpectedTypeException($constraint->fields, 'array');
        }

        if (null !== $constraint->errorPath && !\is_string($constraint->errorPath)) {
            throw new UnexpectedTypeException($constraint->errorPath, 'string or null');
        }

        $fields = (array) $constraint->fields;

        if (0 === \count($fields)) {
            throw new ConstraintDefinitionException('At least one field has to be specified.');
        }

        if (null === $value) {
            return;
        }

        if (!\is_object($value)) {
            throw new UnexpectedValueException($value, 'object');
        }

        $entityClass = $constraint->entityClass ?? $value::class;

        if ($constraint->em) {
            try {
                $em = $this->registry->getManager($constraint->em);
            } catch (\InvalidArgumentException $e) {
                throw new ConstraintDefinitionException(\sprintf('Object manager "%s" does not exist.', $constraint->em), 0, $e);
            }
        } else {
            $em = $this->registry->getManagerForClass($entityClass);

            if (!$em) {
                throw new ConstraintDefinitionException(\sprintf('Unable to find the object manager associated with an entity of class "%s".', $entityClass));
            }
        }

        try {
            $em->getRepository($value::class);
            $isValueEntity = true;
        } catch (ORMMappingException|PersistenceMappingException) {
            $isValueEntity = false;
        }

        $class = $em->getClassMetadata($entityClass);

        $criteria = [];
        $hasIgnorableNullValue = false;

        $fieldValues = $this->getFieldValues($value, $class, $fields, $isValueEntity);

        foreach ($fieldValues as $fieldName => $fieldValue) {
            if (null === $fieldValue && $this->ignoreNullForField($constraint, $fieldName)) {
                $hasIgnorableNullValue = true;

                continue;
            }

            $criteria[$fieldName] = $fieldValue;

            if (\is_object($criteria[$fieldName]) && $class->hasAssociation($fieldName)) {
                /* Ensure the Proxy is initialized before using reflection to
                 * read its identifiers. This is necessary because the wrapped
                 * getter methods in the Proxy are being bypassed.
                 */
                $em->initializeObject($criteria[$fieldName]);
            }
        }

        // validation doesn't fail if one of the fields is null and if null values should be ignored
        if ($hasIgnorableNullValue) {
            return;
        }

        // skip validation if there are no criteria (this can happen when the
        // "ignoreNull" option is enabled and fields to be checked are null
        if (!$criteria) {
            return;
        }

        if (null !== $constraint->entityClass) {
            /* Retrieve repository from given entity name.
             * We ensure the retrieved repository can handle the entity
             * by checking the entity is the same, or subclass of the supported entity.
             */
            $repository = $em->getRepository($constraint->entityClass);
            $supportedClass = $repository->getClassName();

            if ($isValueEntity && !$value instanceof $supportedClass) {
                $class = $em->getClassMetadata($value::class);
                throw new ConstraintDefinitionException(\sprintf('The "%s" entity repository does not support the "%s" entity. The entity should be an instance of or extend "%s".', $constraint->entityClass, $class->getName(), $supportedClass));
            }
        } else {
            $repository = $em->getRepository($value::class);
        }

        $arguments = [$criteria];

        /* If the default repository method is used, it is always enough to retrieve at most two entities because:
         * - No entity returned, the current entity is definitely unique.
         * - More than one entity returned, the current entity cannot be unique.
         * - One entity returned the uniqueness depends on the current entity.
         */
        if ('findBy' === $constraint->repositoryMethod) {
            $arguments = [$criteria, null, 2];
        }

        $result = $repository->{$constraint->repositoryMethod}(...$arguments);

        if ($result instanceof \IteratorAggregate) {
            $result = $result->getIterator();
        }

        /* If the result is a MongoCursor, it must be advanced to the first
         * element. Rewinding should have no ill effect if $result is another
         * iterator implementation.
         */
        if ($result instanceof \Iterator) {
            $result->rewind();
            if ($result instanceof \Countable && 1 < \count($result)) {
                $result = [$result->current(), $result->current()];
            } else {
                $result = $result->valid() && null !== $result->current() ? [$result->current()] : [];
            }
        } elseif (\is_array($result)) {
            reset($result);
        } else {
            $result = null === $result ? [] : [$result];
        }

        /* If no entity matched the query criteria or a single entity matched,
         * which is the same as the entity being validated, the criteria is
         * unique.
         */
        if (!$result || (1 === \count($result) && current($result) === $value)) {
            return;
        }

        /* If a single entity matched the query criteria, which is the same as
         * the entity being updated by validated object, the criteria is unique.
         */
        if (!$isValueEntity && !empty($constraint->identifierFieldNames) && 1 === \count($result)) {
            $fieldValues = $this->getFieldValues($value, $class, $constraint->identifierFieldNames);
            if (array_values($class->getIdentifierFieldNames()) != array_values($constraint->identifierFieldNames)) {
                throw new ConstraintDefinitionException(\sprintf('The "%s" entity identifier field names should be "%s", not "%s".', $entityClass, implode(', ', $class->getIdentifierFieldNames()), implode(', ', $constraint->identifierFieldNames)));
            }

            $entityMatched = true;

            foreach ($constraint->identifierFieldNames as $identifierFieldName) {
                $propertyValue = $this->getPropertyValue($entityClass, $identifierFieldName, current($result));
                if ($fieldValues[$identifierFieldName] !== $propertyValue) {
                    $entityMatched = false;
                    break;
                }
            }

            if ($entityMatched) {
                return;
            }
        }

        $errorPath = $constraint->errorPath ?? current($fields);
        $invalidValue = $criteria[$errorPath] ?? $criteria[current($fields)];

        $this->context->buildViolation($constraint->message)
            ->atPath($errorPath)
            ->setParameter('{{ value }}', $this->formatWithIdentifiers($em, $class, $invalidValue))
            ->setInvalidValue($invalidValue)
            ->setCode(UniqueEntity::NOT_UNIQUE_ERROR)
            ->setCause($result)
            ->addViolation();
    }

    private function ignoreNullForField(UniqueEntity $constraint, string $fieldName): bool
    {
        if (\is_bool($constraint->ignoreNull)) {
            return $constraint->ignoreNull;
        }

        return \in_array($fieldName, (array) $constraint->ignoreNull, true);
    }

    private function formatWithIdentifiers(ObjectManager $em, ClassMetadata $class, mixed $value): string
    {
        if (!\is_object($value) || $value instanceof \DateTimeInterface) {
            return $this->formatValue($value, self::PRETTY_DATE);
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        if ($class->getName() !== $idClass = $value::class) {
            // non-unique value might be a composite PK that consists of other entity objects
            if ($em->getMetadataFactory()->hasMetadataFor($idClass)) {
                $identifiers = $em->getClassMetadata($idClass)->getIdentifierValues($value);
            } else {
                // this case might happen if the non-unique column has a custom doctrine type and its value is an object
                // in which case we cannot get any identifiers for it
                $identifiers = [];
            }
        } else {
            $identifiers = $class->getIdentifierValues($value);
        }

        if (!$identifiers) {
            return \sprintf('object("%s")', $idClass);
        }

        array_walk($identifiers, function (&$id, $field) {
            if (!\is_object($id) || $id instanceof \DateTimeInterface) {
                $idAsString = $this->formatValue($id, self::PRETTY_DATE);
            } else {
                $idAsString = \sprintf('object("%s")', $id::class);
            }

            $id = \sprintf('%s => %s', $field, $idAsString);
        });

        return \sprintf('object("%s") identified by (%s)', $idClass, implode(', ', $identifiers));
    }

    private function getFieldValues(mixed $object, ClassMetadata $class, array $fields, bool $isValueEntity = false): array
    {
        if (!$isValueEntity) {
            $reflectionObject = new \ReflectionObject($object);
        }

        $fieldValues = [];
        $objectClass = $object::class;

        foreach ($fields as $objectFieldName => $entityFieldName) {
            if (!$class->hasField($entityFieldName) && !$class->hasAssociation($entityFieldName)) {
                throw new ConstraintDefinitionException(\sprintf('The field "%s" is not mapped by Doctrine, so it cannot be validated for uniqueness.', $entityFieldName));
            }

            $fieldName = \is_int($objectFieldName) ? $entityFieldName : $objectFieldName;
            if (!$isValueEntity && !$reflectionObject->hasProperty($fieldName)) {
                throw new ConstraintDefinitionException(\sprintf('The field "%s" is not a property of class "%s".', $fieldName, $objectClass));
            }

            if ($isValueEntity && $object instanceof ($class->getName()) && property_exists(OrmClassMetadata::class, 'propertyAccessors')) {
                $fieldValues[$entityFieldName] = $class->propertyAccessors[$fieldName]->getValue($object);
            } elseif ($isValueEntity && $object instanceof ($class->getName())) {
                $fieldValues[$entityFieldName] = $class->reflFields[$fieldName]->getValue($object);
            } else {
                $fieldValues[$entityFieldName] = $this->getPropertyValue($objectClass, $fieldName, $object);
            }
        }

        return $fieldValues;
    }

    private function getPropertyValue(string $class, string $name, mixed $object): mixed
    {
        $property = new \ReflectionProperty($class, $name);

        return $property->getValue($object);
    }
}
