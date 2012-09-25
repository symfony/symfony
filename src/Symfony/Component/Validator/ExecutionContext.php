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
 * Stores the state of the current node in the validation graph.
 *
 * This class is immutable by design.
 *
 * It is used by the GraphWalker to initialize validation of different items
 * and keep track of the violations.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 */
class ExecutionContext
{
    private $globalContext;
    private $propertyPath;
    private $value;
    private $group;
    private $class;
    private $property;

    public function __construct(GlobalExecutionContext $globalContext, $value, $propertyPath, $group, $class = null, $property = null)
    {
        $this->globalContext = $globalContext;
        $this->value = $value;
        $this->propertyPath = $propertyPath;
        $this->group = $group;
        $this->class = $class;
        $this->property = $property;
    }

    public function __clone()
    {
        $this->globalContext = clone $this->globalContext;
    }

    /**
     * Adds a violation at the current node of the validation graph.
     *
     * @param string       $message       The error message.
     * @param array        $params        The parameters parsed into the error message.
     * @param mixed        $invalidValue  The invalid, validated value.
     * @param integer|null $pluralization The number to use to pluralize of the message.
     * @param integer|null $code          The violation code.
     *
     * @api
     */
    public function addViolation($message, array $params = array(), $invalidValue = null, $pluralization = null, $code = null)
    {
        $this->globalContext->addViolation(new ConstraintViolation(
            $message,
            $params,
            $this->globalContext->getRoot(),
            $this->propertyPath,
            // check using func_num_args() to allow passing null values
            func_num_args() >= 3 ? $invalidValue : $this->value,
            $pluralization,
            $code
        ));
    }

    /**
     * Adds a violation at the validation graph node with the given property
     * path.
     *
     * @param string       $propertyPath  The property path for the violation.
     * @param string       $message       The error message.
     * @param array        $params        The parameters parsed into the error message.
     * @param mixed        $invalidValue  The invalid, validated value.
     * @param integer|null $pluralization The number to use to pluralize of the message.
     * @param integer|null $code          The violation code.
     */
    public function addViolationAtPath($propertyPath, $message, array $params = array(), $invalidValue = null, $pluralization = null, $code = null)
    {
        $this->globalContext->addViolation(new ConstraintViolation(
            $message,
            $params,
            $this->globalContext->getRoot(),
            $propertyPath,
            // check using func_num_args() to allow passing null values
            func_num_args() >= 4 ? $invalidValue : $this->value,
            $pluralization,
            $code
        ));
    }

    /**
     * Adds a violation at the validation graph node with the given property
     * path relative to the current property path.
     *
     * @param string       $subPath       The relative property path for the violation.
     * @param string       $message       The error message.
     * @param array        $params        The parameters parsed into the error message.
     * @param mixed        $invalidValue  The invalid, validated value.
     * @param integer|null $pluralization The number to use to pluralize of the message.
     * @param integer|null $code          The violation code.
     */
    public function addViolationAtSubPath($subPath, $message, array $params = array(), $invalidValue = null, $pluralization = null, $code = null)
    {
        $this->globalContext->addViolation(new ConstraintViolation(
            $message,
            $params,
            $this->globalContext->getRoot(),
            $this->getPropertyPath($subPath),
            // check using func_num_args() to allow passing null values
            func_num_args() >= 4 ? $invalidValue : $this->value,
            $pluralization,
            $code
        ));
    }

    /**
     * @return ConstraintViolationList
     *
     * @api
     */
    public function getViolations()
    {
        return $this->globalContext->getViolations();
    }

    public function getRoot()
    {
        return $this->globalContext->getRoot();
    }

    public function getPropertyPath($subPath = null)
    {
        if (null !== $subPath && '' !== $this->propertyPath && '' !== $subPath && '[' !== $subPath[0]) {
            return $this->propertyPath . '.' . $subPath;
        }

        return $this->propertyPath . $subPath;
    }

    public function getCurrentClass()
    {
        return $this->class;
    }

    public function getCurrentProperty()
    {
        return $this->property;
    }

    public function getCurrentValue()
    {
        return $this->value;
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
        return $this->globalContext->getGraphWalker();
    }

    /**
     * @return ClassMetadataFactoryInterface
     */
    public function getMetadataFactory()
    {
        return $this->globalContext->getMetadataFactory();
    }
}
