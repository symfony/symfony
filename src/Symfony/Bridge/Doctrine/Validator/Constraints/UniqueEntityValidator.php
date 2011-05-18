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

use Symfony\Bundle\DoctrineBundle\Registry;
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
     * @var Registry
     */
    private $registry;
    
    /**
     * @param Registry $registry
     */
    public function __construct(Registry $registry)
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
        if (count($constraint->fields) == 0) {
            throw new ConstraintDefinitionException("At least one field has to specified.");
        }
        
        $em = $this->registry->getEntityManager($constraint->em);
        
        $className = $this->context->getCurrentClass();
        $class = $em->getClassMetadata($className);
        
        $criteria = array();
        foreach ($fields as $fieldName) {
            if (!isset($class->reflFields[$fieldName])) {
                throw new ConstraintDefinitionException("Only field names mapped by Doctrine can be validated for uniqueness.");
            }
            
            $criteria[$fieldName] = $class->reflFields[$fieldName]->getValue($entity);
        }
        
        $repository = $em->getRepository($className);
        $result = $repository->findBy($criteria);
        
        if (count($result) > 0 && $result[0] !== $entity) {
            $oldPath = $this->context->getPropertyPath();
            $this->context->setPropertyPath( empty($oldPath) ? $fields[0] : $oldPath . "." . $fields[0]);
            $this->context->addViolation($constraint->message, array(), $criteria[$constraint->fields[0]]);
            $this->context->setPropertyPath($oldPath);
        }
        
        return true; // all true, we added the violation already!
    }
}