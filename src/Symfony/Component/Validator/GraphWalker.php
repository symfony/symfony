<?php

namespace Symfony\Component\Validator;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Mapping\ClassMetadataFactoryInterface;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\MemberMetadata;

class GraphWalker
{
    protected $context;
    protected $validatorFactory;
    protected $metadataFactory;

    public function __construct($root, ClassMetadataFactoryInterface $metadataFactory, ConstraintValidatorFactoryInterface $factory)
    {
        $this->context = new ValidationContext($root, $this, $metadataFactory);
        $this->validatorFactory = $factory;
        $this->metadataFactory = $metadataFactory;
    }

    public function getViolations()
    {
        return $this->context->getViolations();
    }

    public function walkClass(ClassMetadata $metadata, $object, $group, $propertyPath)
    {
        $this->context->setCurrentClass($metadata->getClassName());

        if ($group === Constraint::DEFAULT_GROUP && $metadata->hasGroupSequence()) {
            $groups = $metadata->getGroupSequence();
            foreach ($groups as $group) {
                $this->walkClassForGroup($metadata, $object, $group, $propertyPath, Constraint::DEFAULT_GROUP);

                if (count($this->getViolations()) > 0) {
                    break;
                }
            }
        } else {
            $this->walkClassForGroup($metadata, $object, $group, $propertyPath);
        }
    }

    protected function walkClassForGroup(ClassMetadata $metadata, $object, $group, $propertyPath, $propagatedGroup = null)
    {
        foreach ($metadata->findConstraints($group) as $constraint) {
            $this->walkConstraint($constraint, $object, $group, $propertyPath);
        }

        if ($object !== null) {
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
            $this->walkReference($value, $propagatedGroup ?: $group, $propertyPath);
        }
    }

    protected function walkReference($value, $group, $propertyPath)
    {
        if (null !== $value) {
            if (is_array($value)) {
                foreach ($value as $key => $element) {
                    $this->walkReference($element, $group, $propertyPath.'['.$key.']');
                }
            } else if (!is_object($value)) {
                throw new UnexpectedTypeException($value, 'object or array');
            } else {
                $metadata = $this->metadataFactory->getClassMetadata(get_class($value));
                $this->walkClass($metadata, $value, $group, $propertyPath);
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