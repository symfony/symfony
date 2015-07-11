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
 * Stores the validator's state during validation.
 *
 * For example, let's validate the following object graph:
 *
 * <pre>
 * (Person)---($firstName: string)
 *      \
 *   ($address: Address)---($street: string)
 * </pre>
 *
 * We validate the <tt>Person</tt> instance, which becomes the "root" of the
 * validation run (see {@link getRoot}). The state of the context after the
 * first step will be like this:
 *
 * <pre>
 * (Person)---($firstName: string)
 *    ^ \
 *   ($address: Address)---($street: string)
 * </pre>
 *
 * The validator is stopped at the <tt>Person</tt> node, both the root and the
 * value (see {@link getValue}) of the context point to the <tt>Person</tt>
 * instance. The property path is empty at this point (see {@link getPropertyPath}).
 * The metadata of the context is the metadata of the <tt>Person</tt> node
 * (see {@link getMetadata}).
 *
 * After advancing to the property <tt>$firstName</tt> of the <tt>Person</tt>
 * instance, the state of the context looks like this:
 *
 * <pre>
 * (Person)---($firstName: string)
 *      \              ^
 *   ($address: Address)---($street: string)
 * </pre>
 *
 * The validator is stopped at the property <tt>$firstName</tt>. The root still
 * points to the <tt>Person</tt> instance, because this is where the validation
 * started. The property path is now "firstName" and the current value is the
 * value of that property.
 *
 * After advancing to the <tt>$address</tt> property and then to the
 * <tt>$street</tt> property of the <tt>Address</tt> instance, the context state
 * looks like this:
 *
 * <pre>
 * (Person)---($firstName: string)
 *      \
 *   ($address: Address)---($street: string)
 *                               ^
 * </pre>
 *
 * The validator is stopped at the property <tt>$street</tt>. The root still
 * points to the <tt>Person</tt> instance, but the property path is now
 * "address.street" and the validated value is the value of that property.
 *
 * Apart from the root, the property path and the currently validated value,
 * the execution context also knows the metadata of the current node (see
 * {@link getMetadata}) which for example returns a {@link Mapping\PropertyMetadata}
 * or a {@link Mapping\ClassMetadata} object. he context also contains the
 * validation group that is currently being validated (see {@link getGroup}) and
 * the violations that happened up until now (see {@link getViolations}).
 *
 * Apart from reading the execution context, you can also use
 * {@link addViolation} or {@link addViolationAt} to add new violations and
 * {@link validate} or {@link validateValue} to validate values that the
 * validator otherwise would not reach.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 *
 * @deprecated Deprecated since version 2.5, to be removed in Symfony 3.0.
 *             Use {@link Context\ExecutionContextInterface} instead.
 */
interface ExecutionContextInterface
{
    /**
     * Adds a violation at the current node of the validation graph.
     *
     * Note: the parameters $invalidValue, $plural and $code are deprecated since version 2.5 and will be removed in 3.0.
     *
     * @param string   $message      The error message
     * @param array    $params       The parameters substituted in the error message
     * @param mixed    $invalidValue The invalid, validated value
     * @param int|null $plural       The number to use to pluralize of the message
     * @param int|null $code         The violation code
     *
     * @api
     */
    public function addViolation($message, array $params = array(), $invalidValue = null, $plural = null, $code = null);

    /**
     * Adds a violation at the validation graph node with the given property
     * path relative to the current property path.
     *
     * @param string   $subPath      The relative property path for the violation
     * @param string   $message      The error message
     * @param array    $parameters   The parameters substituted in the error message
     * @param mixed    $invalidValue The invalid, validated value
     * @param int|null $plural       The number to use to pluralize of the message
     * @param int|null $code         The violation code
     *
     * @api
     *
     * @deprecated Deprecated since version 2.5, to be removed in Symfony 3.0.
     *             Use {@link Context\ExecutionContextInterface::buildViolation()}
     *             instead.
     */
    public function addViolationAt($subPath, $message, array $parameters = array(), $invalidValue = null, $plural = null, $code = null);

    /**
     * Validates the given value within the scope of the current validation.
     *
     * The value may be any value recognized by the used metadata factory
     * (see {@link MetadataFactoryInterface::getMetadata}), or an array or a
     * traversable object of such values.
     *
     * Usually you validate a value that is not the current node of the
     * execution context. For this case, you can pass the {@link $subPath}
     * argument which is appended to the current property path when a violation
     * is created. For example, take the following object graph:
     *
     * <pre>
     * (Person)---($address: Address)---($phoneNumber: PhoneNumber)
     *                     ^
     * </pre>
     *
     * When the execution context stops at the <tt>Person</tt> instance, the
     * property path is "address". When you validate the <tt>PhoneNumber</tt>
     * instance now, pass "phoneNumber" as sub path to correct the property path
     * to "address.phoneNumber":
     *
     * <pre>
     * $context->validate($address->phoneNumber, 'phoneNumber');
     * </pre>
     *
     * Any violations generated during the validation will be added to the
     * violation list that you can access with {@link getViolations}.
     *
     * @param mixed                $value    The value to validate.
     * @param string               $subPath  The path to append to the context's property path.
     * @param null|string|string[] $groups   The groups to validate in. If you don't pass any
     *                                       groups here, the current group of the context
     *                                       will be used.
     * @param bool                 $traverse Whether to traverse the value if it is an array
     *                                       or an instance of <tt>\Traversable</tt>.
     * @param bool                 $deep     Whether to traverse the value recursively if
     *                                       it is a collection of collections.
     *
     * @deprecated Deprecated since version 2.5, to be removed in Symfony 3.0.
     *             Use {@link Context\ExecutionContextInterface::getValidator()}
     *             instead.
     */
    public function validate($value, $subPath = '', $groups = null, $traverse = false, $deep = false);

    /**
     * Validates a value against a constraint.
     *
     * Use the parameter <tt>$subPath</tt> to adapt the property path for the
     * validated value. For example, take the following object graph:
     *
     * <pre>
     * (Person)---($address: Address)---($street: string)
     *                     ^
     * </pre>
     *
     * When the validator validates the <tt>Address</tt> instance, the
     * property path stored in the execution context is "address". When you
     * manually validate the property <tt>$street</tt> now, pass the sub path
     * "street" to adapt the full property path to "address.street":
     *
     * <pre>
     * $context->validate($address->street, new NotNull(), 'street');
     * </pre>
     *
     * @param mixed                   $value       The value to validate.
     * @param Constraint|Constraint[] $constraints The constraint(s) to validate against.
     * @param string                  $subPath     The path to append to the context's property path.
     * @param null|string|string[]    $groups      The groups to validate in. If you don't pass any
     *                                             groups here, the current group of the context
     *                                             will be used.
     *
     * @deprecated Deprecated since version 2.5, to be removed in Symfony 3.0.
     *             Use {@link Context\ExecutionContextInterface::getValidator()}
     *             instead.
     */
    public function validateValue($value, $constraints, $subPath = '', $groups = null);

    /**
     * Returns the violations generated by the validator so far.
     *
     * @return ConstraintViolationListInterface The constraint violation list.
     *
     * @api
     */
    public function getViolations();

    /**
     * Returns the value at which validation was started in the object graph.
     *
     * The validator, when given an object, traverses the properties and
     * related objects and their properties. The root of the validation is the
     * object from which the traversal started.
     *
     * The current value is returned by {@link getValue}.
     *
     * @return mixed The root value of the validation.
     */
    public function getRoot();

    /**
     * Returns the value that the validator is currently validating.
     *
     * If you want to retrieve the object that was originally passed to the
     * validator, use {@link getRoot}.
     *
     * @return mixed The currently validated value.
     */
    public function getValue();

    /**
     * Returns the metadata for the currently validated value.
     *
     * With the core implementation, this method returns a
     * {@link Mapping\ClassMetadata} instance if the current value is an object,
     * a {@link Mapping\PropertyMetadata} instance if the current value is
     * the value of a property and a {@link Mapping\GetterMetadata} instance if
     * the validated value is the result of a getter method.
     *
     * If the validated value is neither of these, for example if the validator
     * has been called with a plain value and constraint, this method returns
     * null.
     *
     * @return MetadataInterface|null The metadata of the currently validated
     *                                value.
     */
    public function getMetadata();

    /**
     * Returns the used metadata factory.
     *
     * @return MetadataFactoryInterface The metadata factory.
     *
     * @deprecated Deprecated since version 2.5, to be removed in Symfony 3.0.
     *             Use {@link Context\ExecutionContextInterface::getValidator()}
     *             instead and call
     *             {@link Validator\ValidatorInterface::getMetadataFor()} or
     *             {@link Validator\ValidatorInterface::hasMetadataFor()} there.
     */
    public function getMetadataFactory();

    /**
     * Returns the validation group that is currently being validated.
     *
     * @return string The current validation group.
     */
    public function getGroup();

    /**
     * Returns the class name of the current node.
     *
     * If the metadata of the current node does not implement
     * {@link ClassBasedInterface} or if no metadata is available for the
     * current node, this method returns null.
     *
     * @return string|null The class name or null, if no class name could be found.
     */
    public function getClassName();

    /**
     * Returns the property name of the current node.
     *
     * If the metadata of the current node does not implement
     * {@link PropertyMetadataInterface} or if no metadata is available for the
     * current node, this method returns null.
     *
     * @return string|null The property name or null, if no property name could be found.
     */
    public function getPropertyName();

    /**
     * Returns the property path to the value that the validator is currently
     * validating.
     *
     * For example, take the following object graph:
     *
     * <pre>
     * (Person)---($address: Address)---($street: string)
     * </pre>
     *
     * When the <tt>Person</tt> instance is passed to the validator, the
     * property path is initially empty. When the <tt>$address</tt> property
     * of that person is validated, the property path is "address". When
     * the <tt>$street</tt> property of the related <tt>Address</tt> instance
     * is validated, the property path is "address.street".
     *
     * Properties of objects are prefixed with a dot in the property path.
     * Indices of arrays or objects implementing the {@link \ArrayAccess}
     * interface are enclosed in brackets. For example, if the property in
     * the previous example is <tt>$addresses</tt> and contains an array
     * of <tt>Address</tt> instance, the property path generated for the
     * <tt>$street</tt> property of one of these addresses is for example
     * "addresses[0].street".
     *
     * @param string $subPath Optional. The suffix appended to the current
     *                        property path.
     *
     * @return string The current property path. The result may be an empty
     *                string if the validator is currently validating the
     *                root value of the validation graph.
     */
    public function getPropertyPath($subPath = '');
}
