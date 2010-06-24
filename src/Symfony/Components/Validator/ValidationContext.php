<?php

namespace Symfony\Components\Validator;

use Symfony\Components\Validator\MessageInterpolator\MessageInterpolatorInterface;
use Symfony\Components\Validator\Mapping\ClassMetadataFactoryInterface;

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
    protected $messageInterpolator;

    public function __construct(
        $root,
        GraphWalker $graphWalker,
        ClassMetadataFactoryInterface $metadataFactory,
        MessageInterpolatorInterface $messageInterpolator
    )
    {
        $this->root = $root;
        $this->graphWalker = $graphWalker;
        $this->messageInterpolator = $messageInterpolator;
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
            $this->messageInterpolator->interpolate($message, $params),
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