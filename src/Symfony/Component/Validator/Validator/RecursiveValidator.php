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

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;
use Symfony\Component\Validator\Context\ExecutionContextFactoryInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\MetadataFactoryInterface;
use Symfony\Component\Validator\ObjectInitializerInterface;
use Symfony\Component\Validator\ValidatorInterface as LegacyValidatorInterface;

/**
 * Recursive implementation of {@link ValidatorInterface}.
 *
 * @since  2.5
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RecursiveValidator implements ValidatorInterface, LegacyValidatorInterface
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
     * @var ObjectInitializerInterface[]
     */
    protected $objectInitializers;

    /**
     * Creates a new validator.
     *
     * @param ExecutionContextFactoryInterface    $contextFactory     The factory for
     *                                                                creating new contexts
     * @param MetadataFactoryInterface            $metadataFactory    The factory for
     *                                                                fetching the metadata
     *                                                                of validated objects
     * @param ConstraintValidatorFactoryInterface $validatorFactory   The factory for creating
     *                                                                constraint validators
     * @param ObjectInitializerInterface[]        $objectInitializers The object initializers
     */
    public function __construct(ExecutionContextFactoryInterface $contextFactory, MetadataFactoryInterface $metadataFactory, ConstraintValidatorFactoryInterface $validatorFactory, array $objectInitializers = array())
    {
        $this->contextFactory = $contextFactory;
        $this->metadataFactory = $metadataFactory;
        $this->validatorFactory = $validatorFactory;
        $this->objectInitializers = $objectInitializers;
    }

    /**
     * {@inheritdoc}
     */
    public function startContext($root = null)
    {
        return new RecursiveContextualValidator(
            $this->contextFactory->createContext($this, $root),
            $this->metadataFactory,
            $this->validatorFactory,
            $this->objectInitializers
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
            $this->validatorFactory,
            $this->objectInitializers
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
    public function validate($value, $groups = null, $traverse = false, $deep = false)
    {
        $numArgs = func_num_args();

        // Use new signature if constraints are given in the second argument
        if (self::testConstraints($groups) && ($numArgs < 3 || 3 === $numArgs && self::testGroups($traverse))) {
            // Rename to avoid total confusion ;)
            $constraints = $groups;
            $groups = $traverse;
        } else {
            @trigger_error('The Symfony\Component\Validator\ValidatorInterface::validate method is deprecated in version 2.5 and will be removed in version 3.0. Use the Symfony\Component\Validator\Validator\ValidatorInterface::validate method instead.', E_USER_DEPRECATED);

            $constraints = new Valid(array('traverse' => $traverse, 'deep' => $deep));
        }

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
    public function validatePropertyValue($objectOrClass, $propertyName, $value, $groups = null)
    {
        // If a class name is passed, take $value as root
        return $this->startContext(is_object($objectOrClass) ? $objectOrClass : $value)
            ->validatePropertyValue($objectOrClass, $propertyName, $value, $groups)
            ->getViolations();
    }

    /**
     * {@inheritdoc}
     */
    public function validateValue($value, $constraints, $groups = null)
    {
        @trigger_error('The '.__METHOD__.' method is deprecated in version 2.5 and will be removed in version 3.0. Use the Symfony\Component\Validator\Validator\ValidatorInterface::validate method instead.', E_USER_DEPRECATED);

        return $this->validate($value, $constraints, $groups);
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadataFactory()
    {
        @trigger_error('The '.__METHOD__.' method is deprecated in version 2.5 and will be removed in version 3.0. Use the Symfony\Component\Validator\Validator\ValidatorInterface::getMetadataFor or Symfony\Component\Validator\Validator\ValidatorInterface::hasMetadataFor method instead.', E_USER_DEPRECATED);

        return $this->metadataFactory;
    }

    private static function testConstraints($constraints)
    {
        return null === $constraints || $constraints instanceof Constraint || (is_array($constraints) && (0 === count($constraints) || current($constraints) instanceof Constraint));
    }

    private static function testGroups($groups)
    {
        return null === $groups || is_string($groups) || $groups instanceof GroupSequence || (is_array($groups) && (0 === count($groups) || is_string(current($groups)) || current($groups) instanceof GroupSequence));
    }
}
