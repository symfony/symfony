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

/**
 * @since  %%NextVersion%%
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface ValidatorInterface
{
    /**
     * Validates a value against a constraint or a list of constraints.
     *
     * If no constraint is passed, the constraint
     * {@link \Symfony\Component\Validator\Constraints\Valid} is assumed.
     *
     * @param mixed                   $value       The value to validate.
     * @param Constraint|Constraint[] $constraints The constraint(s) to validate against.
     * @param array|null              $groups      The validation groups to validate.
     *
     * @return ConstraintViolationListInterface A list of constraint violations. If the
     *                                          list is empty, validation succeeded.
     */
    public function validate($value, $constraints = null, $groups = null);

    /**
     * Validates a property of a value against its current value.
     *
     * The accepted values depend on the {@link MetadataFactoryInterface}
     * implementation.
     *
     * @param mixed      $object The value containing the property.
     * @param string     $propertyName        The name of the property to validate.
     * @param array|null $groups          The validation groups to validate.
     *
     * @return ConstraintViolationListInterface A list of constraint violations. If the
     *                                          list is empty, validation succeeded.
     */
    public function validateProperty($object, $propertyName, $groups = null);

    /**
     * Validate a property of a value against a potential value.
     *
     * The accepted values depend on the {@link MetadataFactoryInterface}
     * implementation.
     *
     * @param string     $object The value containing the property.
     * @param string     $propertyName        The name of the property to validate
     * @param string     $value           The value to validate against the
     *                                    constraints of the property.
     * @param array|null $groups          The validation groups to validate.
     *
     * @return ConstraintViolationListInterface A list of constraint violations. If the
     *                                          list is empty, validation succeeded.
     */
    public function validatePropertyValue($object, $propertyName, $value, $groups = null);

    /**
     * @return ContextualValidatorInterface
     */
    public function startContext();

    /**
     * @param ExecutionContextInterface $context
     *
     * @return ContextualValidatorInterface
     */
    public function inContext(ExecutionContextInterface $context);

    public function getMetadataFor($object);

    public function hasMetadataFor($object);
}
