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

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\MemberMetadata;

/**
 * Responsible for walking over and initializing validation on different
 * types of items.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated Deprecated since version 2.2, to be removed in 2.3. This class
 *             has been replaced by {@link ValidationVisitorInterface} and
 *             {@link MetadataInterface}.
 */
class GraphWalker
{
    /**
     * @var ValidationVisitor
     */
    private $visitor;

    /**
     * @var MetadataFactoryInterface
     */
    private $metadataFactory;

    /**
     * @var array
     */
    private $validatedObjects;

    /**
     * Creates a new graph walker.
     *
     * @param ValidationVisitor        $visitor
     * @param MetadataFactoryInterface $metadataFactory
     * @param array                    $validatedObjects
     *
     * @deprecated Deprecated since version 2.2, to be removed in 2.3.
     */
    public function __construct(ValidationVisitor $visitor, MetadataFactoryInterface $metadataFactory, array &$validatedObjects = array())
    {
        $this->visitor = $visitor;
        $this->metadataFactory = $metadataFactory;
        $this->validatedObjects = &$validatedObjects;
    }

    /**
     * @return ConstraintViolationList
     *
     * @deprecated Deprecated since version 2.2, to be removed in 2.3.
     */
    public function getViolations()
    {
        return $this->visitor->getViolations();
    }

    /**
     * Initialize validation on the given object using the given metadata
     * instance and validation group.
     *
     * @param ClassMetadata $metadata
     * @param object        $object       The object to validate
     * @param string        $group        The validator group to use for validation
     * @param string        $propertyPath
     *
     * @deprecated Deprecated since version 2.2, to be removed in 2.3.
     */
    public function walkObject(ClassMetadata $metadata, $object, $group, $propertyPath)
    {
        $hash = spl_object_hash($object);

        // Exit, if the object is already validated for the current group
        if (isset($this->validatedObjects[$hash][$group])) {
            return;
        }

        // Remember validating this object before starting and possibly
        // traversing the object graph
        $this->validatedObjects[$hash][$group] = true;

        $metadata->accept($this->visitor, $object, $group, $propertyPath);
    }

    protected function walkObjectForGroup(ClassMetadata $metadata, $object, $group, $propertyPath, $propagatedGroup = null)
    {
        $metadata->accept($this->visitor, $object, $group, $propertyPath, $propagatedGroup);
    }

    /**
     * Validates a property of a class.
     *
     * @param Mapping\ClassMetadata $metadata
     * @param                       $property
     * @param                       $object
     * @param                       $group
     * @param                       $propertyPath
     * @param null                  $propagatedGroup
     *
     * @throws Exception\UnexpectedTypeException
     *
     * @deprecated Deprecated since version 2.2, to be removed in 2.3.
     */
    public function walkProperty(ClassMetadata $metadata, $property, $object, $group, $propertyPath, $propagatedGroup = null)
    {
        if (!is_object($object)) {
            throw new UnexpectedTypeException($object, 'object');
        }

        foreach ($metadata->getMemberMetadatas($property) as $member) {
            $member->accept($this->visitor, $member->getValue($object), $group, $propertyPath, $propagatedGroup);
        }
    }

    /**
     * Validates a property of a class against a potential value.
     *
     * @param Mapping\ClassMetadata $metadata
     * @param                       $property
     * @param                       $value
     * @param                       $group
     * @param                       $propertyPath
     *
     * @deprecated Deprecated since version 2.2, to be removed in 2.3.
     */
    public function walkPropertyValue(ClassMetadata $metadata, $property, $value, $group, $propertyPath)
    {
        foreach ($metadata->getMemberMetadatas($property) as $member) {
            $member->accept($this->visitor, $value, $group, $propertyPath);
        }
    }

    protected function walkMember(MemberMetadata $metadata, $value, $group, $propertyPath, $propagatedGroup = null)
    {
        $metadata->accept($this->visitor, $value, $group, $propertyPath, $propagatedGroup);
    }

    /**
     * Validates an object or an array.
     *
     * @param      $value
     * @param      $group
     * @param      $propertyPath
     * @param      $traverse
     * @param bool $deep
     *
     * @deprecated Deprecated since version 2.2, to be removed in 2.3.
     */
    public function walkReference($value, $group, $propertyPath, $traverse, $deep = false)
    {
        $this->visitor->validate($value, $group, $propertyPath, $traverse, $deep);
    }

    /**
     * Validates a value against a constraint.
     *
     * @param Constraint $constraint
     * @param            $value
     * @param            $group
     * @param            $propertyPath
     * @param null       $currentClass
     * @param null       $currentProperty
     *
     * @deprecated Deprecated since version 2.2, to be removed in 2.3.
     */
    public function walkConstraint(Constraint $constraint, $value, $group, $propertyPath, $currentClass = null, $currentProperty = null)
    {
        $metadata = null;

        // BC code to make getCurrentClass() and getCurrentProperty() work when
        // called from within this method
        if (null !== $currentClass) {
            $metadata = $this->metadataFactory->getMetadataFor($currentClass);

            if (null !== $currentProperty && $metadata instanceof PropertyMetadataContainerInterface) {
                $metadata = current($metadata->getPropertyMetadata($currentProperty));
            }
        }

        $context = new ExecutionContext(
            $this->visitor,
            $metadata,
            $value,
            $group,
            $propertyPath
        );

        $context->validateValue($value, $constraint);
    }
}
