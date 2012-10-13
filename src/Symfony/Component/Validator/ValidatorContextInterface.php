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

use Symfony\Component\Validator\Mapping\ClassMetadataFactoryInterface;

/**
 * Stores settings for creating a new validator and creates validators
 *
 * The methods in this class are chainable, i.e. they return the context
 * object itself. When you have finished configuring the new validator, call
 * getValidator() to create the it.
 *
 * <code>
 * $validator = $context
 *     ->setClassMetadataFactory($customFactory)
 *     ->getValidator();
 * </code>
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated Deprecated since version 2.1, to be removed in 2.3. Use
 *             {@link Validation::createValidatorBuilder()} instead.
 */
interface ValidatorContextInterface
{
    /**
     * Sets the class metadata factory used in the new validator
     *
     * @param ClassMetadataFactoryInterface $classMetadataFactory The factory instance
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3. Use
     *             {@link Validation::createValidatorBuilder()} instead.
     */
    public function setClassMetadataFactory(ClassMetadataFactoryInterface $classMetadataFactory);

    /**
     * Sets the constraint validator factory used in the new validator
     *
     * @param ConstraintValidatorFactoryInterface $constraintValidatorFactory The factory instance
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3. Use
     *             {@link Validation::createValidatorBuilder()} instead.
     */
    public function setConstraintValidatorFactory(ConstraintValidatorFactoryInterface $constraintValidatorFactory);

    /**
     * Creates a new validator with the settings stored in this context
     *
     * @return ValidatorInterface   The new validator
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3. Use
     *             {@link Validation::createValidator()} instead.
     */
    public function getValidator();
}
