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
use Symfony\Component\Validator\ExecutionContextInterface as LegacyExecutionContextInterface;
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
 *     public function validate($value, Constraint $constraint)
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
 *     public function validate($value, Constraint $constraint)
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
interface ExecutionContextInterface extends LegacyExecutionContextInterface
{
    /**
     * Returns a builder for adding a violation with extended information.
     *
     * Call {@link ConstraintViolationBuilderInterface::addViolation()} to
     * add the violation when you're done with the configuration:
     *
     *     $context->buildViolation('Please enter a number between %min% and %max%.')
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
     * Returns the validator.
     *
     * Useful if you want to validate additional constraints:
     *
     *     public function validate($value, Constraint $constraint)
     *     {
     *         $validator = $this->context->getValidator();
     *
     *         $violations = $validator->validate($value, new Length(array('min' => 3)));
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
     * Returns the currently validated object.
     *
     * If the validator is currently validating a class constraint, the
     * object of that class is returned. If it is a validating a property or
     * getter constraint, the object that the property/getter belongs to is
     * returned.
     *
     * In other cases, null is returned.
     *
     * @return object|null The currently validated object or null
     */
    public function getObject();

    /**
     * Sets the currently validated value.
     *
     * @param mixed                  $value        The validated value
     * @param object|null            $object       The currently validated object
     * @param MetadataInterface|null $metadata     The validation metadata
     * @param string                 $propertyPath The property path to the current value
     *
     * @internal Used by the validator engine. Should not be called by user
     *           code.
     */
    public function setNode($value, $object, MetadataInterface $metadata = null, $propertyPath);

    /**
     * Sets the currently validated group.
     *
     * @param string|null $group The validated group
     *
     * @internal Used by the validator engine. Should not be called by user
     *           code.
     */
    public function setGroup($group);

    /**
     * Sets the currently validated constraint.
     *
     * @param Constraint $constraint The validated constraint
     *
     * @internal Used by the validator engine. Should not be called by user
     *           code.
     */
    public function setConstraint(Constraint $constraint);

    /**
     * Marks an object as validated in a specific validation group.
     *
     * @param string $cacheKey  The hash of the object
     * @param string $groupHash The group's name or hash, if it is group
     *                          sequence
     *
     * @internal Used by the validator engine. Should not be called by user
     *           code.
     */
    public function markGroupAsValidated($cacheKey, $groupHash);

    /**
     * Returns whether an object was validated in a specific validation group.
     *
     * @param string $cacheKey  The hash of the object
     * @param string $groupHash The group's name or hash, if it is group
     *                          sequence
     *
     * @return bool Whether the object was already validated for that
     *              group
     *
     * @internal Used by the validator engine. Should not be called by user
     *           code.
     */
    public function isGroupValidated($cacheKey, $groupHash);

    /**
     * Marks a constraint as validated for an object.
     *
     * @param string $cacheKey       The hash of the object
     * @param string $constraintHash The hash of the constraint
     *
     * @internal Used by the validator engine. Should not be called by user
     *           code.
     */
    public function markConstraintAsValidated($cacheKey, $constraintHash);

    /**
     * Returns whether a constraint was validated for an object.
     *
     * @param string $cacheKey       The hash of the object
     * @param string $constraintHash The hash of the constraint
     *
     * @return bool Whether the constraint was already validated
     *
     * @internal Used by the validator engine. Should not be called by user
     *           code.
     */
    public function isConstraintValidated($cacheKey, $constraintHash);

    /**
     * Marks that an object was initialized.
     *
     * @param string $cacheKey The hash of the object
     *
     * @internal Used by the validator engine. Should not be called by user
     *           code.
     *
     * @see ObjectInitializerInterface
     */
    public function markObjectAsInitialized($cacheKey);

    /**
     * Returns whether an object was initialized.
     *
     * @param string $cacheKey The hash of the object
     *
     * @return bool Whether the object was already initialized
     *
     * @internal Used by the validator engine. Should not be called by user
     *           code.
     *
     * @see ObjectInitializerInterface
     */
    public function isObjectInitialized($cacheKey);
}
