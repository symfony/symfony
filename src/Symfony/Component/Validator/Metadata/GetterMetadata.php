<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.     
 */

namespace Symfony\Component\Validator\Metadata;

use Metadata\MethodMetadata;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Exception\ValidatorException;

class GetterMetadata extends MethodMetadata implements MemberMetadata
{
    public $property;

    public $cascaded = false;

    public $collectionCascaded = false;

    public $constraints = array();

    public function __construct($class, $property)
    {
        $getMethod = 'get' . ucfirst($property);
        $isMethod = 'is' . ucfirst($property);

        if (method_exists($class, $getMethod)) {
            $method = $getMethod;
        } elseif (method_exists($class, $isMethod)) {
            $method = $isMethod;
        } else {
            throw new ValidatorException(sprintf('Neither method %s nor %s exists in class %s', $getMethod, $isMethod, $class));
        }

        $this->property = $property;

        parent::__construct($class, $method);
    }

    /**
     *
     * @return array An array with all Constraint instances belonging to the group
     */
    public function findConstraints($group)
    {
        return array_filter($this->constraints, function (Constraint $constraint) use ($group) {
            return in_array($group, $constraint->groups);
        });
    }

    /**
     * Adds a constraint to this element
     *
     * @param Constraint $constraint
     * @return ClassMetadata
     */
    public function addConstraint(Constraint $constraint)
    {
        if (!in_array(Constraint::PROPERTY_CONSTRAINT, (array) $constraint->getTargets())) {
            throw new ConstraintDefinitionException(sprintf(
                'The constraint %s cannot be put on properties or getters',
                get_class($constraint)
            ));
        }

        if ($constraint instanceof Valid) {
            $this->cascaded = true;
            $this->collectionCascaded = $constraint->traverse;

            return $this;
        }

        $this->constraints[] = $constraint;

        return $this;
    }

    public function isPublic()
    {
        return $this->reflection->isPublic();
    }

    public function isPrivate()
    {
        return $this->reflection->isPrivate();
    }

    public function isProtected()
    {
        return $this->reflection->isProtected();
    }

    public function getClassName()
    {
        return $this->class;
    }

    public function getPropertyName()
    {
        return $this->property;
    }

    public function getValue($object)
    {
        return $this->invoke($object);
    }

    public function isCollectionCascaded()
    {
        return $this->collectionCascaded;
    }

    public function isCascaded()
    {
        return $this->cascaded;
    }

    public function getReflectionMember()
    {
        return $this->reflection;
    }

    public function serialize()
    {
        return serialize(array(
            $this->property,
            $this->cascaded,
            $this->collectionCascaded,
            $this->constraints,
            parent::serialize(),
        ));
    }

    public function unserialize($str)
    {
        list(
            $this->property,
            $this->cascaded,
            $this->collectionCascaded,
            $this->constraints,
            $parentStr
        ) = unserialize($str);

        parent::unserialize($parentStr);
    }

    /**
     * Clones this object.
     */
    public function __clone()
    {
        $constraints = $this->constraints;

        $this->constraints = array();

        foreach ($constraints as $constraint) {
            $this->addConstraint(clone $constraint);
        }
    }
}
