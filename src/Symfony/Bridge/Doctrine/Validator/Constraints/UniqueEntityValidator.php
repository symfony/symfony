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

use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Unique Entity Validator checks if one or a set of fields contain unique values.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class UniqueEntityValidator extends ConstraintValidator
{
    /**
     * @var ManagerRegistry
     */
    private $registry;

    /**
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * @param object $entity
     * @param Constraint $constraint
     * @return bool
     */
    public function isValid($entity, Constraint $constraint)
    {
        if (!is_array($constraint->fields) && !is_string($constraint->fields)) {
            throw new UnexpectedTypeException($constraint->fields, 'array');
        }

        $fields = (array)$constraint->fields;

        if (count($fields) == 0) {
            throw new ConstraintDefinitionException("At least one field has to be specified.");
        }

        if ($constraint->em) {
            $em = $this->registry->getManager($constraint->em);
        } else {
            $em = $this->registry->getManagerForClass(get_class($entity));
        }

        $className = $this->context->getCurrentClass();
        $class = $em->getClassMetadata($className);

        $criteria = array();
        foreach ($fields as $fieldName) {
            if (!isset($class->reflFields[$fieldName])) {
                throw new ConstraintDefinitionException("Only field names mapped by Doctrine can be validated for uniqueness.");
            }

            $criteria[$fieldName] = $class->reflFields[$fieldName]->getValue($entity);

            if ($criteria[$fieldName] === null) {
                return true;
            } else if (isset($class->associationMappings[$fieldName])) {
                $relatedClass = $em->getClassMetadata($class->associationMappings[$fieldName]['targetEntity']);
                $relatedId = $relatedClass->getIdentifierValues($criteria[$fieldName]);

                if (count($relatedId) > 1) {
                    throw new ConstraintDefinitionException(
                        "Associated entities are not allowed to have more than one identifier field to be " .
                        "part of a unique constraint in: " . $class->name . "#" . $fieldName
                    );
                }
                $criteria[$fieldName] = array_pop($relatedId);
            }
        }

        $repository = $em->getRepository($className);
        if ($this->isUniqueKeyAvailable($repository, $class, $entity, $criteria)) {
            return true;
        }

        $oldPath = $this->context->getPropertyPath();
        $this->context->setPropertyPath( empty($oldPath) ? $fields[0] : $oldPath.".".$fields[0]);
        $this->context->addViolation($constraint->message, array(), $criteria[$fields[0]]);
        $this->context->setPropertyPath($oldPath);

        return true; // all true, we added the violation already!
    }

    /**
     *
     * @param EntityRepository $repository
     * @param Doctrine\ORM\Mapping\ClassMetadata $class
     * @param object $entity
     * @param array $criteria
     * @return bool
     */
    protected function isUniqueKeyAvailable($repository, $class, $entity, $criteria)
    {
        // We build a query to fetch possible results with same unique columns but different identifier
        $qb = $repository->createQueryBuilder('e');

        $orx = $qb->expr()->orx();
        foreach($class->identifier as $identifier) {
            $orx->add($qb->expr()->neq('e.'.$identifier, ':'.$identifier));
            $qb->setParameter($identifier, $class->reflFields[$identifier]->getValue($entity));
        }
        $qb = $qb->andWhere($orx);

        foreach ($criteria as $column => $value) {
            $qb = $qb->andWhere($qb->expr()->eq('e.'.$column, ':'.$column));
            $qb->setParameter($column, $value);
        }
        $qb->setMaxResults(1);

        $result = $qb->getQuery()->getArrayResult();

        // if we get no such result, then the unique constraint is validated
        if (0 == count($result)) {
            return true;
        }

        return false;
    }
}
