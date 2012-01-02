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

use Metadata\PropertyMetadata as BasePropertyMetadata;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

class PropertyMetadata extends BasePropertyMetadata implements MemberMetadata
{
    /**
     * @var Boolean $cascaded
     */
    public $cascaded = false;

    /**
     * @var Boolean $collectionCascaded
     */
    public $collectionCascaded = false;

    /**
     * @var array
     */
    public $constraints = array();

    /**
     * Converts a ReflectionException into a ValidatorException.
     *
     * @param string $class
     * @param string $name
     * @throws ValidatorException
     */
    public function __construct($class, $name)
    {
        try {
            parent::__construct($class, $name);
        } catch (\ReflectionException $e) {
            throw new ValidatorException($e->getMessage());
        }
    }

    /**
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
        return $this->name;
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
            $this->cascaded,
            $this->collectionCascaded,
            $this->constraints,
            parent::serialize(),
        ));
    }

    public function unserialize($str)
    {
        list(
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
