<?php

namespace Symfony\Component\Validator;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Symfony\Component\Validator\Mapping\ElementMetadata;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\ClassMetadataFactoryInterface;

class Validator implements ValidatorInterface
{
    protected $metadataFactory;
    protected $validatorFactory;

    public function __construct(
        ClassMetadataFactoryInterface $metadataFactory,
        ConstraintValidatorFactoryInterface $validatorFactory
    )
    {
        $this->metadataFactory = $metadataFactory;
        $this->validatorFactory = $validatorFactory;
    }

    public function validate($object, $groups = null)
    {
        $metadata = $this->metadataFactory->getClassMetadata(get_class($object));

        $walk = function(GraphWalker $walker, $group) use ($metadata, $object) {
            return $walker->walkClass($metadata, $object, $group, '');
        };

        return $this->validateGraph($object, $walk, $groups);
    }

    public function validateProperty($object, $property, $groups = null)
    {
        $metadata = $this->metadataFactory->getClassMetadata(get_class($object));

        $walk = function(GraphWalker $walker, $group) use ($metadata, $property, $object) {
            return $walker->walkProperty($metadata, $property, $object, $group, '');
        };

        return $this->validateGraph($object, $walk, $groups);
    }

    public function validatePropertyValue($class, $property, $value, $groups = null)
    {
        $metadata = $this->metadataFactory->getClassMetadata($class);

        $walk = function(GraphWalker $walker, $group) use ($metadata, $property, $value) {
            return $walker->walkPropertyValue($metadata, $property, $value, $group, '');
        };

        return $this->validateGraph($class, $walk, $groups);
    }

    public function validateValue($value, Constraint $constraint, $groups = null)
    {
        $walk = function(GraphWalker $walker, $group) use ($constraint, $value) {
            return $walker->walkConstraint($constraint, $value, $group, '');
        };

        return $this->validateGraph($value, $walk, $groups);
    }

    protected function validateGraph($root, \Closure $walk, $groups = null)
    {
        $walker = new GraphWalker($root, $this->metadataFactory, $this->validatorFactory);
        $groups = $groups ? (array)$groups : array(Constraint::DEFAULT_GROUP);

        foreach ($groups as $group) {
            $walk($walker, $group);
        }

        return $walker->getViolations();
    }
}