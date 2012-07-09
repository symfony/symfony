<?php

namespace Symfony\Component\Validator;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
interface ValidatorContextInterface
{
    /**
     * Sets the class metadata factory used in the new validator
     *
     * @param ClassMetadataFactoryInterface $classMetadataFactory The factory instance
     */
    public function setClassMetadataFactory(ClassMetadataFactoryInterface $classMetadataFactory);

    /**
     * Sets the constraint validator factory used in the new validator
     *
     * @param ConstraintValidatorFactoryInterface $constraintValidatorFactory The factory instance
     */
    public function setConstraintValidatorFactory(ConstraintValidatorFactoryInterface $constraintValidatorFactory);

    /**
     * Creates a new validator with the settings stored in this context
     *
     * @return ValidatorInterface   The new validator
     */
    public function getValidator();
}
