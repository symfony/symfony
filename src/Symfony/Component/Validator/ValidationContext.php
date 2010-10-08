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

use Symfony\Component\Validator\Mapping\ClassMetadataFactoryInterface;

class ValidationContext
{
    protected $root;
    protected $propertyPath;
    protected $class;
    protected $property;
    protected $group;
    protected $violations;
    protected $graphWalker;
    protected $metadataFactory;

    public function __construct(
        $root,
        GraphWalker $graphWalker,
        ClassMetadataFactoryInterface $metadataFactory
    )
    {
        $this->root = $root;
        $this->graphWalker = $graphWalker;
        $this->metadataFactory = $metadataFactory;
        $this->violations = new ConstraintViolationList();
    }

    public function __clone()
    {
        $this->violations = clone $this->violations;
    }

    public function addViolation($message, array $params, $invalidValue)
    {
        $this->violations->add(new ConstraintViolation(
            $message,
            $params,
            $this->root,
            $this->propertyPath,
            $invalidValue
        ));
    }

    public function getViolations()
    {
        return $this->violations;
    }

    public function getRoot()
    {
        return $this->root;
    }

    public function setPropertyPath($propertyPath)
    {
        $this->propertyPath = $propertyPath;
    }

    public function getPropertyPath()
    {
        return $this->propertyPath;
    }

    public function setCurrentClass($class)
    {
        $this->class = $class;
    }

    public function getCurrentClass()
    {
        return $this->class;
    }

    public function setCurrentProperty($property)
    {
        $this->property = $property;
    }

    public function getCurrentProperty()
    {
        return $this->property;
    }

    public function setGroup($group)
    {
        $this->group = $group;
    }

    public function getGroup()
    {
        return $this->group;
    }

    public function getGraphWalker()
    {
        return $this->graphWalker;
    }

    public function getClassMetadataFactory()
    {
        return $this->metadataFactory;
    }
}