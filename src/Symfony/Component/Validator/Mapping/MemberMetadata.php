<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Mapping;

use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\ValidationVisitorInterface;
use Symfony\Component\Validator\PropertyMetadataInterface as LegacyPropertyMetadataInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

abstract class MemberMetadata extends ElementMetadata implements PropertyMetadataInterface, LegacyPropertyMetadataInterface
{
    public $class;
    public $name;
    public $property;
    private $reflMember = array();

    /**
     * Constructor.
     *
     * @param string $class    The name of the class this member is defined on
     * @param string $name     The name of the member
     * @param string $property The property the member belongs to
     */
    public function __construct($class, $name, $property)
    {
        $this->class = $class;
        $this->name = $name;
        $this->property = $property;
    }

    public function accept(ValidationVisitorInterface $visitor, $value, $group, $propertyPath, $propagatedGroup = null)
    {
        $visitor->visit($this, $value, $group, $propertyPath);

        if ($this->isCascaded()) {
            $visitor->validate($value, $propagatedGroup ?: $group, $propertyPath, $this->isCollectionCascaded(), $this->isCollectionCascadedDeeply());
        }
    }

    /**
     * {@inheritDoc}
     */
    public function addConstraint(Constraint $constraint)
    {
        if (!in_array(Constraint::PROPERTY_CONSTRAINT, (array) $constraint->getTargets())) {
            throw new ConstraintDefinitionException(sprintf(
                'The constraint %s cannot be put on properties or getters',
                get_class($constraint)
            ));
        }

        // BC with Symfony < 2.5
        // Only process if the traversal strategy was not already set by the
        // Traverse constraint
        if ($constraint instanceof Valid && !$this->traversalStrategy) {
            if (true === $constraint->traverse) {
                // Try to traverse cascaded objects, but ignore if they do not
                // implement Traversable
                $this->traversalStrategy = TraversalStrategy::TRAVERSE
                    | TraversalStrategy::IGNORE_NON_TRAVERSABLE;

                if ($constraint->deep) {
                    $this->traversalStrategy |= TraversalStrategy::RECURSIVE;
                }
            } elseif (false === $constraint->traverse) {
                $this->traversalStrategy = TraversalStrategy::NONE;
            }
        }

        parent::addConstraint($constraint);

        return $this;
    }

    /**
     * Returns the names of the properties that should be serialized
     *
     * @return array
     */
    public function __sleep()
    {
        return array_merge(parent::__sleep(), array(
            'class',
            'name',
            'property',
        ));
    }

    /**
     * Returns the name of the member
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns the class this member is defined on
     *
     * @return string
     */
    public function getClassName()
    {
        return $this->class;
    }

    /**
     * Returns the name of the property this member belongs to
     *
     * @return string The property name
     */
    public function getPropertyName()
    {
        return $this->property;
    }

    /**
     * Returns whether this member is public
     *
     * @param object|string $objectOrClassName The object or the class name
     *
     * @return Boolean
     */
    public function isPublic($objectOrClassName)
    {
        return $this->getReflectionMember($objectOrClassName)->isPublic();
    }

    /**
     * Returns whether this member is protected
     *
     * @param object|string $objectOrClassName The object or the class name
     *
     * @return Boolean
     */
    public function isProtected($objectOrClassName)
    {
        return $this->getReflectionMember($objectOrClassName)->isProtected();
    }

    /**
     * Returns whether this member is private
     *
     * @param object|string $objectOrClassName The object or the class name
     *
     * @return Boolean
     */
    public function isPrivate($objectOrClassName)
    {
        return $this->getReflectionMember($objectOrClassName)->isPrivate();
    }

    /**
     * Returns whether objects stored in this member should be validated
     *
     * @return Boolean
     */
    public function isCascaded()
    {
        return (boolean) ($this->cascadingStrategy & CascadingStrategy::CASCADE);
    }

    /**
     * Returns whether arrays or traversable objects stored in this member
     * should be traversed and validated in each entry
     *
     * @return Boolean
     */
    public function isCollectionCascaded()
    {
        return (boolean) ($this->traversalStrategy & TraversalStrategy::TRAVERSE);
    }

    /**
     * Returns whether arrays or traversable objects stored in this member
     * should be traversed recursively for inner arrays/traversable objects
     *
     * @return Boolean
     */
    public function isCollectionCascadedDeeply()
    {
        return (boolean) ($this->traversalStrategy & TraversalStrategy::RECURSIVE);
    }

    /**
     * Returns the Reflection instance of the member
     *
     * @param object|string $objectOrClassName The object or the class name
     *
     * @return object
     */
    public function getReflectionMember($objectOrClassName)
    {
        $className = is_string($objectOrClassName) ? $objectOrClassName : get_class($objectOrClassName);
        if (!isset($this->reflMember[$className])) {
            $this->reflMember[$className] = $this->newReflectionMember($objectOrClassName);
        }

        return $this->reflMember[$className];
    }

    /**
     * Creates a new Reflection instance for the member
     *
     * @param object|string $objectOrClassName The object or the class name
     *
     * @return mixed Reflection class
     */
    abstract protected function newReflectionMember($objectOrClassName);
}
