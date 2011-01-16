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

use Symfony\Component\Validator\Constraint;

/**
 * Validates a given value.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
interface ValidatorInterface
{
    /**
     * Validate the given object.
     *
     * @param object $object The object to validate
     * @param array|null $groups The validator groups to use for validating
     * @return ConstraintViolationList
     */
    function validate($object, $groups = null);

    /**
     * Validate a single property of an object against its current value.
     *
     * @param object $object The object to validate
     * @param string $property The name of the property to validate
     * @param array|null $groups The validator groups to use for validating
     * @return ConstraintViolationList
     */
    function validateProperty($object, $property, $groups = null);

    /**
     * Validate a single property of an object against the given value.
     *
     * @param string $class The class on which the property belongs
     * @param string $property The name of the property to validate
     * @param array|null $groups The validator groups to use for validating
     * @return ConstraintViolationList
     */
    function validatePropertyValue($class, $property, $value, $groups = null);

    /**
     * Validates a given value against a specific Constraint.
     *
     * @param mixed $value The value to validate
     * @param Constraint $constraint The constraint to validate against
     * @param array|null $groups The validator groups to use for validating
     * @return ConstraintViolationList
     */
    function validateValue($value, Constraint $constraint, $groups = null);
}