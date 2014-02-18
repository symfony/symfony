<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Context;

use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Mapping\MetadataInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

/**
 * The context of a validation run.
 *
 * The context collects all violations generated during the validation. By
 * default, validators execute all validations in a new context:
 *
 *     $violations = $validator->validateObject($object);
 *
 * When you make another call to the validator, while the validation is in
 * progress, the violations will be isolated from each other:
 *
 *     public function validate($value, Constraint $constraint)
 *     {
 *         $validator = $this->context->getValidator();
 *
 *         // The violations are not added to $this->context
 *         $violations = $validator->validateObject($value);
 *     }
 *
 * However, if you want to add the violations to the current context, use the
 * {@link ValidatorInterface::inContext()} method:
 *
 *     public function validate($value, Constraint $constraint)
 *     {
 *         $validator = $this->context->getValidator();
 *
 *         // The violations are added to $this->context
 *         $validator
 *             ->inContext($this->context)
 *             ->validateObject($value)
 *         ;
 *     }
 *
 * Additionally, the context provides information about the current state of
 * the validator, such as the currently validated class, the name of the
 * currently validated property and more. These values change over time, so you
 * cannot store a context and expect that the methods still return the same
 * results later on.
 *
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface ExecutionContextInterface
{
    /**
     * Adds a violation at the current node of the validation graph.
     *
     * @param string $message    The error message
     * @param array  $parameters The parameters substituted in the error message
     */
    public function addViolation($message, array $parameters = array());

    /**
     * Returns a builder for adding a violation with extended information.
     *
     * Call {@link ConstraintViolationBuilderInterface::addViolation()} to
     * add the violation when you're done with the configuration:
     *
     *     $context->buildViolation('Please enter a number between %min% and %max.')
     *         ->setParameter('%min%', 3)
     *         ->setParameter('%max%', 10)
     *         ->setTranslationDomain('number_validation')
     *         ->addViolation();
     *
     * @param string $message    The error message
     * @param array  $parameters The parameters substituted in the error message
     *
     * @return ConstraintViolationBuilderInterface The violation builder
     */
    public function buildViolation($message, array $parameters = array());

    /**
     * Returns the violations generated in this context.
     *
     * @return ConstraintViolationListInterface The constraint violations
     */
    public function getViolations();

    /**
     * Returns the validator.
     *
     * Useful if you want to validate additional constraints:
     *
     *     public function validate($value, Constraint $constraint)
     *     {
     *         $validator = $this->context->getValidator();
     *
     *         $violations = $validator->validateValue($value, new Length(array('min' => 3)));
     *
     *         if (count($violations) > 0) {
     *             // ...
     *         }
     *     }
     *
     * @return ValidatorInterface
     */
    public function getValidator();

    /**
     * Returns the root value of the object graph.
     *
     * The validator, when given an object, traverses the properties and
     * related objects and their properties. The root of the validation is the
     * object at which the traversal started.
     *
     * The current value is returned by {@link getValue()}.
     *
     * @return mixed|null The root value of the validation or null, if no value
     *                    is currently being validated
     */
    public function getRoot();

    /**
     * Returns the value that the validator is currently validating.
     *
     * If you want to retrieve the object that was originally passed to the
     * validator, use {@link getRoot()}.
     *
     * @return mixed|null The currently validated value or null, if no value is
     *                    currently being validated
     */
    public function getValue();

    /**
     * Returns the metadata for the currently validated value.
     *
     * With the core implementation, this method returns a
     * {@link Mapping\ClassMetadata} instance if the current value is an object,
     * a {@link Mapping\PropertyMetadata} instance if the current value is
     * the value of a property and a {@link Mapping\GetterMetadata} instance if
     * the validated value is the result of a getter method. The metadata can
     * also be an {@link Mapping\AdHocMetadata} if the current value does not
     * belong to any structural element.
     *
     * @return MetadataInterface|null The metadata of the currently validated
     *                                value or null, if no value is currently
     *                                being validated
     */
    public function getMetadata();

    /**
     * Returns the validation group that is currently being validated.
     *
     * @return string|GroupSequence|null The current validation group or null,
     *                                   if no value is currently being
     *                                   validated
     */
    public function getGroup();

    /**
     * Returns the class name of the current node.
     *
     * If the metadata of the current node does not implement
     * {@link ClassBasedInterface}, this method returns null.
     *
     * @return string|null The class name or null, if no class name could be
     *                     found
     */
    public function getClassName();

    /**
     * Returns the property name of the current node.
     *
     * If the metadata of the current node does not implement
     * {@link PropertyMetadataInterface}, this method returns null.
     *
     * @return string|null The property name or null, if no property name could
     *                     be found
     */
    public function getPropertyName();

    /**
     * Returns the property path to the value that the validator is currently
     * validating.
     *
     * For example, take the following object graph:
     *
     *     (Person)---($address: Address)---($street: string)
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
     * of <tt>Address</tt> instances, the property path generated for the
     * <tt>$street</tt> property of one of these addresses is for example
     * "addresses[0].street".
     *
     * @param string $subPath Optional. The suffix appended to the current
     *                        property path
     *
     * @return string The current property path. The result may be an empty
     *                string if the validator is currently validating the
     *                root value of the validation graph
     */
    public function getPropertyPath($subPath = '');
}
