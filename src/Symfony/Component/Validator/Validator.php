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

use Symfony\Component\Validator\Mapping\ElementMetadata;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\ClassMetadataFactoryInterface;

/**
 * The default implementation of the ValidatorInterface.
 *
 * This service can be used to validate objects, properties and raw values
 * against constraints.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class Validator implements ValidatorInterface
{
    protected $metadataFactory;
    protected $validatorFactory;

    public function __construct(
        ClassMetadataFactoryInterface $metadataFactory,
        ConstraintValidatorFactoryInterface $validatorFactory
    )
    {
        $this->metadataFactory = $metadataFactory;
        $this->validatorFactory = $validatorFactory;
    }

    /**
     * {@inheritDoc}
     */
    public function validate($object, $groups = null)
    {
        $metadata = $this->metadataFactory->getClassMetadata(get_class($object));

        $walk = function(GraphWalker $walker, $group) use ($metadata, $object) {
            return $walker->walkObject($metadata, $object, $group, '');
        };

        return $this->validateGraph($object, $walk, $groups);
    }

    /**
     * {@inheritDoc}
     */
    public function validateProperty($object, $property, $groups = null)
    {
        $metadata = $this->metadataFactory->getClassMetadata(get_class($object));

        $walk = function(GraphWalker $walker, $group) use ($metadata, $property, $object) {
            return $walker->walkProperty($metadata, $property, $object, $group, '');
        };

        return $this->validateGraph($object, $walk, $groups);
    }

    /**
     * {@inheritDoc}
     */
    public function validatePropertyValue($class, $property, $value, $groups = null)
    {
        $metadata = $this->metadataFactory->getClassMetadata($class);

        $walk = function(GraphWalker $walker, $group) use ($metadata, $property, $value) {
            return $walker->walkPropertyValue($metadata, $property, $value, $group, '');
        };

        return $this->validateGraph($class, $walk, $groups);
    }

    /**
     * {@inheritDoc}
     */
    public function validateValue($value, Constraint $constraint, $groups = null)
    {
        $walk = function(GraphWalker $walker, $group) use ($constraint, $value) {
            return $walker->walkConstraint($constraint, $value, $group, '');
        };

        return $this->validateGraph($value, $walk, $groups);
    }

    protected function validateGraph($root, \Closure $walk, $groups = null)
    {
        $walker = new GraphWalker($root, $this->metadataFactory, $this->validatorFactory);
        $groups = $groups ? (array) $groups : array(Constraint::DEFAULT_GROUP);

        foreach ($groups as $group) {
            $walk($walker, $group);
        }

        return $walker->getViolations();
    }
}