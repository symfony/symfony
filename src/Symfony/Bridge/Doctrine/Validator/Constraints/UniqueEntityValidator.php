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

use Symfony\Bridge\Doctrine\RegistryInterface;
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
     * @var RegistryInterface
     */
    private $registry;

    /**
     * @param RegistryInterface $registry
     */
    public function __construct(RegistryInterface $registry)
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

        $em = $this->registry->getEntityManager($constraint->em);

        $className = $this->context->getCurrentClass();
        $class = $em->getClassMetadata($className);

        $criteria = array();
        foreach ($fields as $fieldName) {
            if (!isset($class->reflFields[$fieldName])) {
                throw new ConstraintDefinitionException("Only field names mapped by Doctrine can be validated for uniqueness.");
            }
            
            $value = $class->reflFields[$fieldName]->getValue($entity);
            
            if ($value) {
	            $criteria[$fieldName] = $value;
            }
        }
        
        $repository = $em->getRepository($className);
        $qb = $repository->createQueryBuilder('e');
        
        // Construct query
        $x = 0;
        foreach($criteria as $key => $value) {
        	$qb = $qb->orWhere($qb->expr()->eq("e.$key", '?' . $x++));
        }
        
        $qb->setParameters(array_values($criteria));
        
        // Get result
        $result = $qb->getQuery()->getResult();
        
        // If at least one field not unique
        if (count($result) > 0 && $result[0] !== $entity) {
            $oldPath = $this->context->getPropertyPath();
            
            // Browse all fields
            foreach($criteria as $key => $value) {
            	// Get getter name (can't access private member)
            	$getter = 'get' . ucfirst($key);
            	
            	// If not unique
            	if ($result[0]->$getter() == $value) {
	            	$this->context->setPropertyPath( empty($oldPath) ? $key : $oldPath.".".$key);
	            	$this->context->addViolation($constraint->message, array(), $value);
    	        	$this->context->setPropertyPath($oldPath);
            	}
            }
        }

        return true; // all true, we added the violation already!
    }
}
