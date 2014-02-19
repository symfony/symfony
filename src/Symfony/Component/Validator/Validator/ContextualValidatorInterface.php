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

/**
 * @since  %%NextVersion%%
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface ContextualValidatorInterface
{
    /**
     * @param string $subPath
     *
     * @return ContextualValidatorInterface This validator
     */
    public function atPath($subPath);

    /**
     * Validates a value against a constraint or a list of constraints.
     *
     * @param mixed                   $value       The value to validate.
     * @param Constraint|Constraint[] $constraints The constraint(s) to validate against.
     * @param array|null              $groups      The validation groups to validate.
     *
     * @return ContextualValidatorInterface This validator
     */
    public function validate($value, $constraints, $groups = null);

    /**
     * Validates a value.
     *
     * The accepted values depend on the {@link MetadataFactoryInterface}
     * implementation.
     *
     * @param mixed      $object The value to validate
     * @param array|null $groups The validation groups to validate.
     *
     * @return ContextualValidatorInterface This validator
     */
    public function validateObject($object, $groups = null);

    public function validateCollection($collection, $groups = null, $deep = false);

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
     * @return ContextualValidatorInterface This validator
     */
    public function validateProperty($object, $propertyName, $groups = null);

    /**
     * Validate a property of a value against a potential value.
     *
     * The accepted values depend on the {@link MetadataFactoryInterface}
     * implementation.
     *
     * @param string     $object       The value containing the property.
     * @param string     $propertyName The name of the property to validate
     * @param string     $value        The value to validate against the
     *                                 constraints of the property.
     * @param array|null $groups       The validation groups to validate.
     *
     * @return ContextualValidatorInterface This validator
     */
    public function validatePropertyValue($object, $propertyName, $value, $groups = null);

    /**
     * @return ConstraintViolationListInterface
     */
    public function getViolations();
}
