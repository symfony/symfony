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
use Symfony\Component\Validator\Mapping\ClassMetadataFactoryAdapter;

/**
 * Default implementation of ValidatorContextInterface
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated Deprecated since version 2.1, to be removed in 2.3. Use
 *             {@link Validation::createValidatorBuilder()} instead.
 */
class ValidatorContext implements ValidatorContextInterface
{
    /**
     * @var MetadataFactoryInterface
     */
    private $metadataFactory;

    /**
     * The class metadata factory used in the new validator
     * @var ClassMetadataFactoryInterface
     */
    protected $classMetadataFactory = null;

    /**
     * The constraint validator factory used in the new validator
     * @var ConstraintValidatorFactoryInterface
     */
    protected $constraintValidatorFactory = null;

    /**
     * {@inheritDoc}
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3. Use
     *             {@link Validation::createValidatorBuilder()} instead.
     */
    public function setClassMetadataFactory(ClassMetadataFactoryInterface $classMetadataFactory)
    {
        trigger_error('setClassMetadataFactory() is deprecated since version 2.1 and will be removed in 2.3. Use Validation::createValidatorBuilder() instead.', E_USER_DEPRECATED);

        if ($classMetadataFactory instanceof MetadataFactoryInterface) {
            $this->metadataFactory = $classMetadataFactory;
        } else {
            $this->metadataFactory = new ClassMetadataFactoryAdapter($classMetadataFactory);
        }

        $this->classMetadataFactory = $classMetadataFactory;

        return $this;
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3. Use
     *             {@link Validation::createValidatorBuilder()} instead.
     */
    public function setConstraintValidatorFactory(ConstraintValidatorFactoryInterface $constraintValidatorFactory)
    {
        trigger_error('setConstraintValidatorFactory() is deprecated since version 2.1 and will be removed in 2.3. Use Validation::createValidatorBuilder() instead.', E_USER_DEPRECATED);

        $this->constraintValidatorFactory = $constraintValidatorFactory;

        return $this;
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3. Use
     *             {@link Validation::createValidator()} instead.
     */
    public function getValidator()
    {
        trigger_error('getValidator() is deprecated since version 2.1 and will be removed in 2.3. Use Validation::createValidator() instead.', E_USER_DEPRECATED);

        return new Validator(
            $this->metadataFactory,
            $this->constraintValidatorFactory,
            new DefaultTranslator()
        );
    }

    /**
     * Returns the class metadata factory used in the new validator
     *
     * @return ClassMetadataFactoryInterface  The factory instance
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3.
     */
    public function getClassMetadataFactory()
    {
        trigger_error('getClassMetadataFactory() is deprecated since version 2.1 and will be removed in 2.3.', E_USER_DEPRECATED);

        return $this->classMetadataFactory;
    }

    /**
     * Returns the constraint validator factory used in the new validator
     *
     * @return ConstraintValidatorFactoryInterface  The factory instance
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3.
     */
    public function getConstraintValidatorFactory()
    {
        trigger_error('getConstraintValidatorFactory() is deprecated since version 2.1 and will be removed in 2.3.', E_USER_DEPRECATED);

        return $this->constraintValidatorFactory;
    }
}
