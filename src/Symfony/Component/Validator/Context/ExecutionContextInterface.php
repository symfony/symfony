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

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Mapping;
use Symfony\Component\Validator\Mapping\MetadataInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

/**
 * The context of a validation run.
 *
 * The context collects all violations generated during the validation. By
 * default, validators execute all validations in a new context:
 *
 *     $violations = $validator->validate($object);
 *
 * When you make another call to the validator, while the validation is in
 * progress, the violations will be isolated from each other:
 *
 *     public function validate(mixed $value, Constraint $constraint): void
 *     {
 *         $validator = $this->context->getValidator();
 *
 *         // The violations are not added to $this->context
 *         $violations = $validator->validate($value);
 *     }
 *
 * However, if you want to add the violations to the current context, use the
 * {@link ValidatorInterface::inContext()} method:
 *
 *     public function validate(mixed $value, Constraint $constraint): void
 *     {
 *         $validator = $this->context->getValidator();
 *
 *         // The violations are added to $this->context
 *         $validator
 *             ->inContext($this->context)
 *             ->validate($value)
 *         ;
 *     }
 *
 * Additionally, the context provides information about the current state of
 * the validator, such as the currently validated class, the name of the
 * currently validated property and more. These values change over time, so you
 * cannot store a context and expect that the methods still return the same
 * results later on.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface ExecutionContextInterface
{
    /**
     * Adds a violation at the current node of the validation graph.
     *
     * @param string|\Stringable $message The error message as a string or a stringable object
     * @param array              $params  The parameters substituted in the error message
     *
     * @return void
     */
    public function addViolation(string $message, array $params = []);

    /**
     * Returns a builder for adding a violation with extended information.
     *
     * Call {@link ConstraintViolationBuilderInterface::addViolation()} to
     * add the violation when you're done with the configuration:
     *
     *     $context->buildViolation('Please enter a number between %min% and %max%.')
     *         ->setParameter('%min%', '3')
     *         ->setParameter('%max%', '10')
     *         ->setTranslationDomain('number_validation')
     *         ->addViolation();
     *
     * @param string|\Stringable $message    The error message as a string or a stringable object
     * @param array              $parameters The parameters substituted in the error message
     */
    public function buildViolation(string $message, array $parameters = []): ConstraintViolationBuilderInterface;

    /**
     * Returns the validator.
     *
     * Useful if you want to validate additional constraints:
     *
     *     public function validate(mixed $value, Constraint $constraint): void
     *     {
     *         $validator = $this->context->getValidator();
     *
     *         $violations = $validator->validate($value, new Length(['min' => 3]));
     *
     *         if (count($violations) > 0) {
     *             // ...
     *         }
     *     }
     */
    public function getValidator(): ValidatorInterface;

    /**
     * Returns the currently validated object.
     *
     * If the validator is currently validating a class constraint, the
     * object of that class is returned. If it is validating a property or
     * getter constraint, the object that the property/getter belongs to is
     * returned.
     *
     * In other cases, null is returned.
     */
    public function getObject(): ?object;

    /**
     * Warning: Should not be called by user code, to be used by the validator engine only.
     *
     * @param object|null $object       The currently validated object
     * @param string      $propertyPath The property path to the current value
     *
     * @return void
     */
    public function setNode(mixed $value, ?object $object, ?MetadataInterface $metadata, string $propertyPath);

    /**
     * Warning: Should not be called by user code, to be used by the validator engine only.
     *
     * @param string|null $group The validated group
     *
     * @return void
     */
    public function setGroup(?string $group);

    /**
     * Warning: Should not be called by user code, to be used by the validator engine only.
     *
     * @return void
     */
    public function setConstraint(Constraint $constraint);

    /**
     * Warning: Should not be called by user code, to be used by the validator engine only.
     *
     * @param string $cacheKey  The hash of the object
     * @param string $groupHash The group's name or hash, if it is group
     *                          sequence
     *
     * @return void
     */
    public function markGroupAsValidated(string $cacheKey, string $groupHash);

    /**
     * Warning: Should not be called by user code, to be used by the validator engine only.
     *
     * @param string $cacheKey  The hash of the object
     * @param string $groupHash The group's name or hash, if it is group
     *                          sequence
     */
    public function isGroupValidated(string $cacheKey, string $groupHash): bool;

    /**
     * Warning: Should not be called by user code, to be used by the validator engine only.
     *
     * @param string $cacheKey       The hash of the object
     * @param string $constraintHash The hash of the constraint
     *
     * @return void
     */
    public function markConstraintAsValidated(string $cacheKey, string $constraintHash);

    /**
     * Warning: Should not be called by user code, to be used by the validator engine only.
     *
     * @param string $cacheKey       The hash of the object
     * @param string $constraintHash The hash of the constraint
     */
    public function isConstraintValidated(string $cacheKey, string $constraintHash): bool;

    /**
     * Warning: Should not be called by user code, to be used by the validator engine only.
     *
     * @param string $cacheKey The hash of the object
     *
     * @see ObjectInitializerInterface
     */
    public function markObjectAsInitialized(string $cacheKey): void;

    /**
     * Warning: Should not be called by user code, to be used by the validator engine only.
     *
     * @param string $cacheKey The hash of the object
     *
     * @see ObjectInitializerInterface
     */
    public function isObjectInitialized(string $cacheKey): bool;

    /**
     * Returns the violations generated by the validator so far.
     */
    public function getViolations(): ConstraintViolationListInterface;

    /**
     * Returns the value at which validation was started in the object graph.
     *
     * The validator, when given an object, traverses the properties and
     * related objects and their properties. The root of the validation is the
     * object from which the traversal started.
     *
     * The current value is returned by {@link getValue}.
     */
    public function getRoot(): mixed;

    /**
     * Returns the value that the validator is currently validating.
     *
     * If you want to retrieve the object that was originally passed to the
     * validator, use {@link getRoot}.
     */
    public function getValue(): mixed;

    /**
     * Returns the metadata for the currently validated value.
     *
     * With the core implementation, this method returns a
     * {@link Mapping\ClassMetadataInterface} instance if the current value is an object,
     * a {@link Mapping\PropertyMetadata} instance if the current value is
     * the value of a property and a {@link Mapping\GetterMetadata} instance if
     * the validated value is the result of a getter method.
     *
     * If the validated value is neither of these, for example if the validator
     * has been called with a plain value and constraint, this method returns
     * null.
     */
    public function getMetadata(): ?MetadataInterface;

    /**
     * Returns the validation group that is currently being validated.
     */
    public function getGroup(): ?string;

    /**
     * Returns the class name of the current node.
     *
     * If the metadata of the current node does not implement
     * {@link Mapping\ClassMetadataInterface} or if no metadata is available for the
     * current node, this method returns null.
     */
    public function getClassName(): ?string;

    /**
     * Returns the property name of the current node.
     *
     * If the metadata of the current node does not implement
     * {@link PropertyMetadataInterface} or if no metadata is available for the
     * current node, this method returns null.
     */
    public function getPropertyName(): ?string;

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
    public function getPropertyPath(string $subPath = ''): string;
}
