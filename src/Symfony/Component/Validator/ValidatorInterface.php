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

use Symfony\Component\Validator\Constraint;

/**
 * Validates a given value.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 *
 * @api
 */
interface ValidatorInterface
{
    /**
     * Validate the given object.
     *
     * @param object $object The object to validate
     * @param array|null $groups The validator groups to use for validating
     *
     * @return ConstraintViolationList
     *
     * @api
     */
    function validate($object, $groups = null);

    /**
     * Validate a single property of an object against its current value.
     *
     * @param object $object The object to validate
     * @param string $property The name of the property to validate
     * @param array|null $groups The validator groups to use for validating
     *
     * @return ConstraintViolationList
     *
     * @api
     */
    function validateProperty($object, $property, $groups = null);

    /**
     * Validate a single property of an object against the given value.
     *
     * @param string     $class    The class on which the property belongs
     * @param string     $property The name of the property to validate
     * @param string     $value
     * @param array|null $groups   The validator groups to use for validating
     *
     * @return ConstraintViolationList
     *
     * @api
     */
    function validatePropertyValue($class, $property, $value, $groups = null);

    /**
     * Validates a given value against a specific Constraint.
     *
     * @param mixed $value The value to validate
     * @param Constraint $constraint The constraint to validate against
     * @param array|null $groups The validator groups to use for validating
     *
     * @return ConstraintViolationList
     *
     * @api
     */
    function validateValue($value, Constraint $constraint, $groups = null);

    /**
     * Returns the factory for ClassMetadata instances
     *
     * @return Mapping\ClassMetadataFactoryInterface
     *
     * @api
     */
    function getMetadataFactory();
}
