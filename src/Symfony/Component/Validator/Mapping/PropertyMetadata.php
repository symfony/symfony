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

use Symfony\Component\Validator\Exception\ValidatorException;

class PropertyMetadata extends MemberMetadata
{
    private $reflClass;

    /**
     * Constructor.
     *
     * @param string $class The class this property is defined on
     * @param string $name  The name of this property
     */
    public function __construct($class, $name)
    {
        $this->reflClass = new \ReflectionClass($class);

        if (!$this->reflClass->hasMethod('__get') && !property_exists($class, $name)) {
            throw new ValidatorException(sprintf('Property %s does not exists in class %s', $name, $class));
        }
        
        parent::__construct($class, $name, $name);
    }

    /**
     * {@inheritDoc}
     */
    public function getValue($object)
    {
        if ($this->reflClass->hasMethod('__get')) {
            // needed to support magic method __get
            $property = $this->getName();
            return $object->$property;
        }
        return $this->getReflectionMember()->getValue($object);
    }

    /**
     * {@inheritDoc}
     */
    protected function newReflectionMember()
    {
        $member = new \ReflectionProperty($this->getClassName(), $this->getName());
        $member->setAccessible(true);

        return $member;
    }
}