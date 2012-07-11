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
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Mapping\ClassMetadataFactoryInterface;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\MemberMetadata;

/**
 * Responsible for walking over and initializing validation on different
 * types of items.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class GraphWalker
{
    private $globalContext;
    private $validatorFactory;
    private $metadataFactory;
    private $validatorInitializers = array();
    private $validatedObjects = array();

    public function __construct($root, ClassMetadataFactoryInterface $metadataFactory, ConstraintValidatorFactoryInterface $factory, array $validatorInitializers = array())
    {
        $this->globalContext = new GlobalExecutionContext($root, $this, $metadataFactory);
        $this->validatorFactory = $factory;
        $this->metadataFactory = $metadataFactory;
        $this->validatorInitializers = $validatorInitializers;
    }

    /**
     * @return ConstraintViolationList
     */
    public function getViolations()
    {
        return $this->globalContext->getViolations();
    }

    /**
     * Initialize validation on the given object using the given metadata
     * instance and validation group.
     *
     * @param ClassMetadata $metadata
     * @param object        $object       The object to validate
     * @param string        $group        The validator group to use for validation
     * @param string        $propertyPath
     */
    public function walkObject(ClassMetadata $metadata, $object, $group, $propertyPath)
    {
        foreach ($this->validatorInitializers as $initializer) {
            if (!$initializer instanceof ObjectInitializerInterface) {
                throw new \LogicException('Validator initializers must implement ObjectInitializerInterface.');
            }
            $initializer->initialize($object);
        }

        if ($group === Constraint::DEFAULT_GROUP && ($metadata->hasGroupSequence() || $metadata->isGroupSequenceProvider())) {
            if ($metadata->hasGroupSequence()) {
                $groups = $metadata->getGroupSequence();
            } else {
                $groups = $object->getGroupSequence();
            }

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
        if (isset($this->validatedObjects[$hash][$group])) {
                return;
        }

        // Remember validating this object before starting and possibly
        // traversing the object graph
        $this->validatedObjects[$hash][$group] = true;

        $currentClass = $metadata->getClassName();

        foreach ($metadata->findConstraints($group) as $constraint) {
            $this->walkConstraint($constraint, $object, $group, $propertyPath, $currentClass);
        }

        if (null !== $object) {
            $pathPrefix = empty($propertyPath) ? '' : $propertyPath.'.';
            foreach ($metadata->getConstrainedProperties() as $property) {
                $this->walkProperty($metadata, $property, $object, $group, $pathPrefix.$property, $propagatedGroup);
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
        $currentClass = $metadata->getClassName();
        $currentProperty = $metadata->getPropertyName();

        foreach ($metadata->findConstraints($group) as $constraint) {
            $this->walkConstraint($constraint, $value, $group, $propertyPath, $currentClass, $currentProperty);
        }

        if ($metadata->isCascaded()) {
            $this->walkReference($value, $propagatedGroup ?: $group, $propertyPath, $metadata->isCollectionCascaded(), $metadata->isCollectionCascadedDeeply());
        }
    }

    public function walkReference($value, $group, $propertyPath, $traverse, $deep = false)
    {
        if (null !== $value) {
            if (!is_object($value) && !is_array($value)) {
                throw new UnexpectedTypeException($value, 'object or array');
            }

            if ($traverse && (is_array($value) || $value instanceof \Traversable)) {
                foreach ($value as $key => $element) {
                    // Ignore any scalar values in the collection
                    if (is_object($element) || is_array($element)) {
                        // Only repeat the traversal if $deep is set
                        $this->walkReference($element, $group, $propertyPath.'['.$key.']', $deep, $deep);
                    }
                }
            }

            if (is_object($value)) {
                $metadata = $this->metadataFactory->getClassMetadata(get_class($value));
                $this->walkObject($metadata, $value, $group, $propertyPath);
            }
        }
    }

    public function walkConstraint(Constraint $constraint, $value, $group, $propertyPath, $currentClass = null, $currentProperty = null)
    {
        $validator = $this->validatorFactory->getInstance($constraint);

        $localContext = new ExecutionContext(
            $this->globalContext,
            $value,
            $propertyPath,
            $group,
            $currentClass,
            $currentProperty
        );

        $validator->initialize($localContext);
        $validator->validate($value, $constraint);
    }
}
