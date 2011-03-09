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

use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Mapping\ClassMetadataFactoryInterface;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\MemberMetadata;

/**
 * Responsible for walking over and initializing validation on different
 * types of items.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class GraphWalker
{
    protected $context;
    protected $validatorFactory;
    protected $metadataFactory;
    protected $validatedObjects = array();

    public function __construct($root, ClassMetadataFactoryInterface $metadataFactory, ConstraintValidatorFactoryInterface $factory)
    {
        $this->context = new ExecutionContext($root, $this, $metadataFactory);
        $this->validatorFactory = $factory;
        $this->metadataFactory = $metadataFactory;
    }

    /**
     * @return ConstraintViolationList
     */
    public function getViolations()
    {
        return $this->context->getViolations();
    }

    /**
     * Initialize validation on the given object using the given metadata
     * instance and validation group.
     *
     * @param ClassMetadata $metadata
     * @param  object $object The object to validate
     * @param  string $group The validator group to use for validation
     * @param  string $propertyPath
     */
    public function walkObject(ClassMetadata $metadata, $object, $group, $propertyPath)
    {
        $this->context->setCurrentClass($metadata->getClassName());

        if ($group === Constraint::DEFAULT_GROUP && $metadata->hasGroupSequence()) {
            $groups = $metadata->getGroupSequence();
            foreach ($groups as $group) {
                $this->walkObjectForGroup($metadata, $object, $group, $propertyPath, Constraint::DEFAULT_GROUP);

                if (count($this->getViolations()) > 0) {
                    break;
                }
            }
        } else {
            $this->walkObjectForGroup($metadata, $object, $group, $propertyPath);
        }
    }

    protected function walkObjectForGroup(ClassMetadata $metadata, $object, $group, $propertyPath, $propagatedGroup = null)
    {
        $hash = spl_object_hash($object);

        // Exit, if the object is already validated for the current group
        if (isset($this->validatedObjects[$hash])) {
            if (isset($this->validatedObjects[$hash][$group])) {
                return;
            }
        } else {
            $this->validatedObjects[$hash] = array();
        }

        // Remember validating this object before starting and possibly
        // traversing the object graph
        $this->validatedObjects[$hash][$group] = true;

        foreach ($metadata->findConstraints($group) as $constraint) {
            $this->walkConstraint($constraint, $object, $group, $propertyPath);
        }

        if (null !== $object) {
            foreach ($metadata->getConstrainedProperties() as $property) {
                $localPropertyPath = empty($propertyPath) ? $property : $propertyPath.'.'.$property;

                $this->walkProperty($metadata, $property, $object, $group, $localPropertyPath, $propagatedGroup);
            }
        }
    }

    public function walkProperty(ClassMetadata $metadata, $property, $object, $group, $propertyPath, $propagatedGroup = null)
    {
        foreach ($metadata->getMemberMetadatas($property) as $member) {
            $this->walkMember($member, $member->getValue($object), $group, $propertyPath, $propagatedGroup);
        }
    }

    public function walkPropertyValue(ClassMetadata $metadata, $property, $value, $group, $propertyPath)
    {
        foreach ($metadata->getMemberMetadatas($property) as $member) {
            $this->walkMember($member, $value, $group, $propertyPath);
        }
    }

    protected function walkMember(MemberMetadata $metadata, $value, $group, $propertyPath, $propagatedGroup = null)
    {
        $this->context->setCurrentProperty($metadata->getPropertyName());

        foreach ($metadata->findConstraints($group) as $constraint) {
            $this->walkConstraint($constraint, $value, $group, $propertyPath);
        }

        if ($metadata->isCascaded()) {
            $this->walkReference($value, $propagatedGroup ?: $group, $propertyPath, $metadata->isCollectionCascaded());
        }
    }

    public function walkReference($value, $group, $propertyPath, $traverse)
    {
        if (null !== $value) {
            if (!is_object($value) && !is_array($value)) {
                throw new UnexpectedTypeException($value, 'object or array');
            }

            if ($traverse && (is_array($value) || $value instanceof \Traversable)) {
                foreach ($value as $key => $element) {
                    // Ignore any scalar values in the collection
                    if (is_object($element) || is_array($element)) {
                        $this->walkReference($element, $group, $propertyPath.'['.$key.']', $traverse);
                    }
                }
            }

            if (is_object($value)) {
                $metadata = $this->metadataFactory->getClassMetadata(get_class($value));
                $this->walkObject($metadata, $value, $group, $propertyPath);
            }
        }
    }

    public function walkConstraint(Constraint $constraint, $value, $group, $propertyPath)
    {
        $validator = $this->validatorFactory->getInstance($constraint);

        $this->context->setPropertyPath($propertyPath);
        $this->context->setGroup($group);

        $validator->initialize($this->context);

        if (!$validator->isValid($value, $constraint)) {
            $this->context->addViolation(
                $validator->getMessageTemplate(),
                $validator->getMessageParameters(),
                $value
            );
        }
    }
}