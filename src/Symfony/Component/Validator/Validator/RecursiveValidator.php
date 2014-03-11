<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Validator;

use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;
use Symfony\Component\Validator\Context\ExecutionContextFactoryInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\MetadataFactoryInterface;

/**
 * Recursive implementation of {@link ValidatorInterface}.
 *
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RecursiveValidator implements ValidatorInterface
{
    /**
     * @var ExecutionContextFactoryInterface
     */
    protected $contextFactory;

    /**
     * @var MetadataFactoryInterface
     */
    protected $metadataFactory;

    /**
     * @var ConstraintValidatorFactoryInterface
     */
    protected $validatorFactory;

    /**
     * Creates a new validator.
     *
     * @param ExecutionContextFactoryInterface    $contextFactory   The factory for
     *                                                              creating new contexts
     * @param MetadataFactoryInterface            $metadataFactory  The factory for
     *                                                              fetching the metadata
     *                                                              of validated objects
     * @param ConstraintValidatorFactoryInterface $validatorFactory The factory for creating
     *                                                              constraint validators
     */
    public function __construct(ExecutionContextFactoryInterface $contextFactory, MetadataFactoryInterface $metadataFactory, ConstraintValidatorFactoryInterface $validatorFactory)
    {
        $this->contextFactory = $contextFactory;
        $this->metadataFactory = $metadataFactory;
        $this->validatorFactory = $validatorFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function startContext($root = null)
    {
        return new RecursiveContextualValidator(
            $this->contextFactory->createContext($this, $root),
            $this->metadataFactory,
            $this->validatorFactory
        );
    }

    /**
     * {@inheritdoc}
     */
    public function inContext(ExecutionContextInterface $context)
    {
        return new RecursiveContextualValidator(
            $context,
            $this->metadataFactory,
            $this->validatorFactory
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadataFor($object)
    {
        return $this->metadataFactory->getMetadataFor($object);
    }

    /**
     * {@inheritdoc}
     */
    public function hasMetadataFor($object)
    {
        return $this->metadataFactory->hasMetadataFor($object);
    }

    /**
     * {@inheritdoc}
     */
    public function validate($value, $constraints = null, $groups = null)
    {
        return $this->startContext($value)
            ->validate($value, $constraints, $groups)
            ->getViolations();
    }

    /**
     * {@inheritdoc}
     */
    public function validateProperty($object, $propertyName, $groups = null)
    {
        return $this->startContext($object)
            ->validateProperty($object, $propertyName, $groups)
            ->getViolations();
    }

    /**
     * {@inheritdoc}
     */
    public function validatePropertyValue($object, $propertyName, $value, $groups = null)
    {
        return $this->startContext($object)
            ->validatePropertyValue($object, $propertyName, $value, $groups)
            ->getViolations();
    }
}
