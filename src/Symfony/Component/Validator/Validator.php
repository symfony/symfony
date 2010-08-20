<?php

namespace Symfony\Component\Validator;

use Symfony\Component\Validator\Mapping\ElementMetadata;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\ClassMetadataFactoryInterface;
use Symfony\Component\Validator\MessageInterpolator\MessageInterpolatorInterface;

class Validator implements ValidatorInterface
{
    protected $metadataFactory;
    protected $validatorFactory;
    protected $messageInterpolator;

    public function __construct(
        ClassMetadataFactoryInterface $metadataFactory,
        ConstraintValidatorFactoryInterface $validatorFactory,
        MessageInterpolatorInterface $messageInterpolator
    )
    {
        $this->metadataFactory = $metadataFactory;
        $this->validatorFactory = $validatorFactory;
        $this->messageInterpolator = $messageInterpolator;
    }

    public function validate($object, $groups = null)
    {
        $metadata = $this->metadataFactory->getClassMetadata(get_class($object));
        $groupChain = $this->buildGroupChain($metadata, $groups);

        $closure = function(GraphWalker $walker, $group) use ($metadata, $object) {
            return $walker->walkClass($metadata, $object, $group, '');
        };

        return $this->validateGraph($object, $closure, $groupChain);
    }

    public function validateProperty($object, $property, $groups = null)
    {
        $metadata = $this->metadataFactory->getClassMetadata(get_class($object));
        $groupChain = $this->buildGroupChain($metadata, $groups);

        $closure = function(GraphWalker $walker, $group) use ($metadata, $property, $object) {
            return $walker->walkProperty($metadata, $property, $object, $group, '');
        };

        return $this->validateGraph($object, $closure, $groupChain);
    }

    public function validatePropertyValue($class, $property, $value, $groups = null)
    {
        $metadata = $this->metadataFactory->getClassMetadata($class);
        $groupChain = $this->buildGroupChain($metadata, $groups);

        $closure = function(GraphWalker $walker, $group) use ($metadata, $property, $value) {
            return $walker->walkPropertyValue($metadata, $property, $value, $group, '');
        };

        return $this->validateGraph($object, $closure, $groupChain);
    }

    public function validateValue($value, Constraint $constraint, $groups = null)
    {
        $groupChain = $this->buildSimpleGroupChain($groups);

        $closure = function(GraphWalker $walker, $group) use ($constraint, $value) {
            return $walker->walkConstraint($constraint, $value, $group, '');
        };

        return $this->validateGraph($value, $closure, $groupChain);
    }

    protected function validateGraph($root, \Closure $closure, GroupChain $groupChain)
    {
        $walker = new GraphWalker($root, $this->metadataFactory, $this->validatorFactory, $this->messageInterpolator);

        foreach ($groupChain->getGroups() as $group) {
            $closure($walker, $group);
        }

        foreach ($groupChain->getGroupSequences() as $sequence) {
            $violationCount = count($walker->getViolations());

            foreach ($sequence as $group) {
                $closure($walker, $group);

                if (count($walker->getViolations()) > $violationCount) {
                    break;
                }
            }
        }

        return $walker->getViolations();
    }

    protected function buildSimpleGroupChain($groups)
    {
        if (is_null($groups)) {
            $groups = array(Constraint::DEFAULT_GROUP);
        } else {
            $groups = (array)$groups;
        }

        $chain = new GroupChain();

        foreach ($groups as $group) {
            $chain->addGroup($group);
        }

        return $chain;
    }

    protected function buildGroupChain(ClassMetadata $metadata, $groups)
    {
        if (is_null($groups)) {
            $groups = array(Constraint::DEFAULT_GROUP);
        } else {
            $groups = (array)$groups;
        }

        $chain = new GroupChain();

        foreach ($groups as $group) {
            if ($group == Constraint::DEFAULT_GROUP && $metadata->hasGroupSequence()) {
                $chain->addGroupSequence($metadata->getGroupSequence());
            } else {
                $chain->addGroup($group);
            }
        }

        return $chain;
    }
}