<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Context\ExecutionContextManagerInterface;
use Symfony\Component\Validator\MetadataFactoryInterface;
use Symfony\Component\Validator\NodeTraverser\NodeTraverserInterface;

/**
 * @since  %%NextVersion%%
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ContextualValidator extends AbstractValidator implements ContextualValidatorInterface
{
    /**
     * @var ExecutionContextManagerInterface
     */
    private $context;

    public function __construct(NodeTraverserInterface $nodeTraverser, MetadataFactoryInterface $metadataFactory, ExecutionContextInterface $context)
    {
        parent::__construct($nodeTraverser, $metadataFactory);

        $this->context = $context;
        $this->defaultPropertyPath = $context->getPropertyPath();
        $this->defaultGroups = array($context->getGroup());
    }

    public function atPath($subPath)
    {
        $this->defaultPropertyPath = $this->context->getPropertyPath($subPath);
    }

    /**
     * Validates a value.
     *
     * The accepted values depend on the {@link MetadataFactoryInterface}
     * implementation.
     *
     * @param mixed      $object The value to validate
     * @param array|null $groups The validation groups to validate.
     *
     * @return ConstraintViolationListInterface A list of constraint violations. If the
     *                                          list is empty, validation succeeded.
     */
    public function validateObject($object, $groups = null)
    {
        $this->traverseObject($object, $groups);

        return $this->context->getViolations();
    }

    /**
     * Validates a property of a value against its current value.
     *
     * The accepted values depend on the {@link MetadataFactoryInterface}
     * implementation.
     *
     * @param mixed      $object       The value containing the property.
     * @param string     $propertyName The name of the property to validate.
     * @param array|null $groups       The validation groups to validate.
     *
     * @return ConstraintViolationListInterface A list of constraint violations. If the
     *                                          list is empty, validation succeeded.
     */
    public function validateProperty($object, $propertyName, $groups = null)
    {
        $this->traverseProperty($object, $propertyName, $groups);

        return $this->context->getViolations();
    }

    /**
     * Validate a property of a value against a potential value.
     *
     * The accepted values depend on the {@link MetadataFactoryInterface}
     * implementation.
     *
     * @param string     $object          The value containing the property.
     * @param string     $propertyName    The name of the property to validate
     * @param string     $value           The value to validate against the
     *                                    constraints of the property.
     * @param array|null $groups          The validation groups to validate.
     *
     * @return ConstraintViolationListInterface A list of constraint violations. If the
     *                                          list is empty, validation succeeded.
     */
    public function validatePropertyValue($object, $propertyName, $value, $groups = null)
    {
        $this->traversePropertyValue($object, $propertyName, $value, $groups);

        return $this->context->getViolations();
    }

    /**
     * Validates a value against a constraint or a list of constraints.
     *
     * @param mixed                   $value       The value to validate.
     * @param Constraint|Constraint[] $constraints The constraint(s) to validate against.
     * @param array|null              $groups      The validation groups to validate.
     *
     * @return ConstraintViolationListInterface A list of constraint violations. If the
     *                                          list is empty, validation succeeded.
     */
    public function validateValue($value, $constraints, $groups = null)
    {
        $this->traverseValue($value, $constraints, $groups);

        return $this->context->getViolations();
    }
}
