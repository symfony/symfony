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

/**
 * Validates values and graphs of objects and arrays.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 *
 * @deprecated Deprecated since version 2.5, to be removed in Symfony 3.0.
 *             Use {@link Validator\ValidatorInterface} instead.
 */
interface ValidatorInterface
{
    /**
     * Validates a value.
     *
     * The accepted values depend on the {@link MetadataFactoryInterface}
     * implementation.
     *
     * The signature changed with Symfony 2.5 (see
     * {@link Validator\ValidatorInterface::validate()}. This signature will be
     * disabled in Symfony 3.0.
     *
     * @param mixed      $value    The value to validate
     * @param array|null $groups   The validation groups to validate.
     * @param bool       $traverse Whether to traverse the value if it is traversable.
     * @param bool       $deep     Whether to traverse nested traversable values recursively.
     *
     * @return ConstraintViolationListInterface A list of constraint violations. If the
     *                                          list is empty, validation succeeded.
     *
     * @api
     */
    public function validate($value, $groups = null, $traverse = false, $deep = false);

    /**
     * Validates a property of a value against its current value.
     *
     * The accepted values depend on the {@link MetadataFactoryInterface}
     * implementation.
     *
     * @param mixed      $containingValue The value containing the property.
     * @param string     $property        The name of the property to validate.
     * @param array|null $groups          The validation groups to validate.
     *
     * @return ConstraintViolationListInterface A list of constraint violations. If the
     *                                          list is empty, validation succeeded.
     *
     * @api
     */
    public function validateProperty($containingValue, $property, $groups = null);

    /**
     * Validate a property of a value against a potential value.
     *
     * The accepted values depend on the {@link MetadataFactoryInterface}
     * implementation.
     *
     * @param mixed      $containingValue The value containing the property.
     * @param string     $property        The name of the property to validate
     * @param string     $value           The value to validate against the
     *                                    constraints of the property.
     * @param array|null $groups          The validation groups to validate.
     *
     * @return ConstraintViolationListInterface A list of constraint violations. If the
     *                                          list is empty, validation succeeded.
     *
     * @api
     */
    public function validatePropertyValue($containingValue, $property, $value, $groups = null);

    /**
     * Validates a value against a constraint or a list of constraints.
     *
     * @param mixed                   $value       The value to validate.
     * @param Constraint|Constraint[] $constraints The constraint(s) to validate against.
     * @param array|null              $groups      The validation groups to validate.
     *
     * @return ConstraintViolationListInterface A list of constraint violations. If the
     *                                          list is empty, validation succeeded.
     *
     * @api
     *
     * @deprecated Renamed to {@link Validator\ValidatorInterface::validate()}
     *             in Symfony 2.5. Will be removed in Symfony 3.0.
     */
    public function validateValue($value, $constraints, $groups = null);

    /**
     * Returns the factory for metadata instances.
     *
     * @return MetadataFactoryInterface The metadata factory.
     *
     * @api
     *
     * @deprecated Deprecated since version 2.5, to be removed in Symfony 3.0.
     *             Use {@link Validator\ValidatorInterface::getMetadataFor()} or
     *             {@link Validator\ValidatorInterface::hasMetadataFor()}
     *             instead.
     */
    public function getMetadataFactory();
}
