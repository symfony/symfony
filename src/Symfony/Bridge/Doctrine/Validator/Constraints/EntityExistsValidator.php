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

use Doctrine\DBAL\Types\ConversionException;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\MappingException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validates that a value references an existing entity.
 *
 * @author Evgen Yurchenko <evgenijvy@gmail.com>
 */
class EntityExistsValidator extends ConstraintValidator
{
    public function __construct(
        private readonly ManagerRegistry $registry,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof EntityExists) {
            throw new UnexpectedTypeException($constraint, EntityExists::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if ($constraint->em) {
            try {
                $em = $this->registry->getManager($constraint->em);
            } catch (\InvalidArgumentException $e) {
                throw new ConstraintDefinitionException(\sprintf('Object manager "%s" does not exist.', $constraint->em), 0, $e);
            }
        } else {
            $em = $this->registry->getManagerForClass($constraint->entityClass);

            if (!$em) {
                throw new ConstraintDefinitionException(\sprintf('Unable to find the object manager associated with an entity of class "%s".', $constraint->entityClass));
            }
        }

        try {
            $classMetadata = $em->getClassMetadata($constraint->entityClass);
        } catch (MappingException $e) {
            throw new ConstraintDefinitionException(\sprintf('Unable to load class "%s".', $constraint->entityClass), 0, $e);
        }

        $repository = $em->getRepository($constraint->entityClass);

        if ($constraint->repositoryMethod) {
            if (!method_exists($repository, $constraint->repositoryMethod)) {
                throw new ConstraintDefinitionException(\sprintf('Repository for class "%s" does not have method "%s".', $constraint->entityClass, $constraint->repositoryMethod));
            }

            $data = $repository->{$constraint->repositoryMethod}($value);
        } else {
            try {
                // the manager converts the value to the field's type itself, against its
                // own connection; a value it cannot convert can reference no entity, which
                // is a problem with the value rather than with the constraint
                $data = $repository->findOneBy([$this->resolveField($constraint, $classMetadata) => $value]);
            } catch (ConversionException) {
                $this->context->buildViolation($constraint->invalidMessage)
                    ->setParameter('{{ value }}', $this->formatValue($value))
                    ->setInvalidValue($value)
                    ->setCode(EntityExists::INVALID_VALUE_ERROR)
                    ->addViolation();

                return;
            }
        }

        if (!$data) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setInvalidValue($value)
                ->setCode(EntityExists::ENTITY_NOT_FOUND_ERROR)
                ->addViolation();
        }
    }

    private function resolveField(EntityExists $constraint, ClassMetadata $classMetadata): string
    {
        if (!$field = $constraint->identifierField) {
            $identifierFieldNames = $classMetadata->getIdentifierFieldNames();

            if (1 !== \count($identifierFieldNames)) {
                throw new ConstraintDefinitionException(\sprintf('Entity "%s" has multiple identifier fields. Use the "identifierField" option to specify which one to use.', $constraint->entityClass));
            }

            return $identifierFieldNames[0];
        }

        if (!$classMetadata->hasField($field) && !$classMetadata->hasAssociation($field)) {
            throw new ConstraintDefinitionException(\sprintf('Entity "%s" does not have a field named "%s".', $constraint->entityClass, $field));
        }

        return $field;
    }
}
