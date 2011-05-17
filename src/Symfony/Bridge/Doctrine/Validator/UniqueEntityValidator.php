<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Validator;

class UniqueEntityValidator extends ConstraintValidator
{
    /**
     * @var Registry
     */
    private $registry;
    
    public function __construct(Registry $registry)
    {
        $this->registry = $registry;
    }
    
    public function isValid($entity, Constraint $constraint)
    {
        $emName = $constraint->entityManagerName ?: $this->registry->getDefaultConnectionName();
        $em = $this->registry->getEntityManager($emName);
        
        $className = $this->context->getCurrentClass();
        $class = $em->getClassMetadata($className);
        
        $criteria = array();
        foreach ($contraint->fields AS $fieldName) {
            $criteria[$fieldName] = $class->reflFields[$fieldName]->getValue($entity);
        }
        
        $repository = $em->getRepository($className);
        $result = $repository->findBy($criteria);
        
        if (count($result) > 0 && $result[0] !== $entity) {
            $this->setMessage($constraint->message);
            return false;
        }
        return true;
    }
}