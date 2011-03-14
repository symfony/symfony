<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator;

use Symfony\Component\Validator\Mapping\ClassMetadataFactoryInterface;

/**
 * The central object representing a single validation process.
 *
 * This object is used by the GraphWalker to initialize validation of different
 * items and keep track of the violations.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class ExecutionContext
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

    /**
     * @return ConstraintViolationList
     */
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

    /**
     * @return GraphWalker
     */
    public function getGraphWalker()
    {
        return $this->graphWalker;
    }

    /**
     * @return ClassMetadataFactoryInterface
     */
    public function getMetadataFactory()
    {
        return $this->metadataFactory;
    }
}