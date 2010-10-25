<?php

namespace Symfony\Component\Validator\Mapping;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Symfony\Component\Validator\Exception\ValidatorException;

class PropertyMetadata extends MemberMetadata
{
    /**
     * Constructor.
     *
     * @param string $class The class this property is defined on
     * @param string $name  The name of this property
     */
    public function __construct($class, $name)
    {
        if (!property_exists($class, $name)) {
            throw new ValidatorException(sprintf('Property %s does not exists in class %s', $name, $class));
        }

        parent::__construct($class, $name, $name);
    }

    /**
     * {@inheritDoc}
     */
    public function getValue($object)
    {
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