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

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Composite;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

/**
 * Stores all metadata needed for validating a class property.
 *
 * The method of accessing the property's value must be specified by subclasses
 * by implementing the {@link newReflectionMember()} method.
 *
 * This class supports serialization and cloning.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @see PropertyMetadataInterface
 */
abstract class MemberMetadata extends GenericMetadata implements PropertyMetadataInterface
{
    private string $class;
    private string $name;
    private string $property;

    /**
     * @var \ReflectionMethod[]|\ReflectionProperty[]
     */
    private array $reflMember = [];

    /**
     * @param string $class    The name of the class this member is defined on
     * @param string $name     The name of the member
     * @param string $property The property the member belongs to
     */
    public function __construct(string $class, string $name, string $property)
    {
        $this->class = $class;
        $this->name = $name;
        $this->property = $property;
    }

    public function addConstraint(Constraint $constraint): static
    {
        $this->checkConstraint($constraint);

        parent::addConstraint($constraint);

        return $this;
    }

    public function __serialize(): array
    {
        if (self::class === (new \ReflectionMethod($this, '__sleep'))->class || self::class !== (new \ReflectionMethod($this, '__serialize'))->class) {
            return parent::__serialize() + [
                'class' => $this->class,
                'name' => $this->name,
                'property' => $this->property,
            ];
        }

        trigger_deprecation('symfony/validator', '7.4', 'Implementing "%s::__sleep()" is deprecated, use "__serialize()" instead.', get_debug_type($this));

        $data = [];
        foreach ($this->__sleep() as $key) {
            try {
                if (($r = new \ReflectionProperty($this, $key))->isInitialized($this)) {
                    $data[$key] = $r->getValue($this);
                }
            } catch (\ReflectionException) {
                $data[$key] = $this->$key;
            }
        }

        return $data;
    }

    /**
     * @deprecated since Symfony 7.4, will be replaced by `__serialize()` in 8.0
     */
    public function __sleep(): array
    {
        trigger_deprecation('symfony/validator', '7.4', 'Calling "%s::__sleep()" is deprecated, use "__serialize()" instead.', get_debug_type($this));

        return [
            'constraints',
            'constraintsByGroup',
            'cascadingStrategy',
            'traversalStrategy',
            'autoMappingStrategy',
            'class',
            'name',
            'property',
        ];
    }

    /**
     * Returns the name of the property or its getter.
     */
    public function getName(): string
    {
        return $this->name;
    }

    public function getClassName(): string
    {
        return $this->class;
    }

    public function getPropertyName(): string
    {
        return $this->property;
    }

    public function isPublic(object|string $objectOrClassName): bool
    {
        return $this->getReflectionMember($objectOrClassName)->isPublic();
    }

    public function isProtected(object|string $objectOrClassName): bool
    {
        return $this->getReflectionMember($objectOrClassName)->isProtected();
    }

    public function isPrivate(object|string $objectOrClassName): bool
    {
        return $this->getReflectionMember($objectOrClassName)->isPrivate();
    }

    public function getReflectionMember(object|string $objectOrClassName): \ReflectionMethod|\ReflectionProperty
    {
        $className = \is_string($objectOrClassName) ? $objectOrClassName : $objectOrClassName::class;
        if (!isset($this->reflMember[$className])) {
            $this->reflMember[$className] = $this->newReflectionMember($objectOrClassName);
        }

        return $this->reflMember[$className];
    }

    /**
     * Creates a new reflection instance for accessing the member's value.
     */
    abstract protected function newReflectionMember(object|string $objectOrClassName): \ReflectionMethod|\ReflectionProperty;

    private function checkConstraint(Constraint $constraint): void
    {
        if (!\in_array(Constraint::PROPERTY_CONSTRAINT, (array) $constraint->getTargets(), true)) {
            throw new ConstraintDefinitionException(\sprintf('The constraint "%s" cannot be put on properties or getters.', get_debug_type($constraint)));
        }

        if ($constraint instanceof Composite) {
            foreach ($constraint->getNestedConstraints() as $nestedConstraint) {
                $this->checkConstraint($nestedConstraint);
            }
        }
    }
}
