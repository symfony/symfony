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
    public function validate($value, $constraints = null, $groups = null)
    {
        // The following code must be removed in 3.0
        $numArgs = func_num_args();
        if ($numArgs > 3) {
            trigger_error('The '.__METHOD__.' method is deprecated in version 2.5 and will be removed in version 3.0. Use the Symfony\Component\Validator\Validator\ValidatorInterface::validate method instead.', E_USER_DEPRECATED);
            $traverse = func_get_arg(3);
            $deep = func_get_arg(4);
            $constraints = new Valid(array('traverse' => $traverse, 'deep' => $deep));

            if (self::testConstraints($groups)) {
                if ((!$traverse && !$deep) || self::testGroups($traverse)) {
                    $constraints = $groups;
                    $groups = $traverse;
                }
            }
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
     * @internal
     * @deprecated since version 2.5, to be removed in 3.0.
     */
    private static function testConstraints($constraints)
    {
        return null === $constraints || $constraints instanceof Constraint || (is_array($constraints) && current($constraints) instanceof Constraint);
    }

    /**
     * @internal
     * @deprecated since version 2.5, to be removed in 3.0.
     */
    private static function testGroups($groups)
    {
        return null === $groups || is_string($groups) || $groups instanceof GroupSequence || (is_array($groups) && (is_string(current($groups)) || current($groups) instanceof GroupSequence));
    }
}
