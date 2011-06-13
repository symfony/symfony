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
