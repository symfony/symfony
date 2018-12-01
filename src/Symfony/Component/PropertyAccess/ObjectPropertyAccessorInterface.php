<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess;

/**
 * Writes and reads values to/from an object.
 *
 * @author Fabien Bourigault <bourigaultfabien@gmail.com>
 */
interface ObjectPropertyAccessorInterface
{
    /**
     * Returns the object property value.
     *
     * @param object $object   The object to get the value from
     * @param string $property The property to get the value from
     *
     * @return mixed The object property value
     *
     * @throws Exception\AccessException If the property does not exist
     */
    public function getPropertyValue($object, string $property);

    /**
     * Sets the object property value.
     *
     * @param object $object   The object to modify
     * @param string $property The property to modify
     * @param mixed  $value    The value to set in the object property
     *
     * @throws Exception\InvalidArgumentException If the value is incompatible with property type
     * @throws Exception\AccessException          If the property does not exist
     */
    public function setPropertyValue($object, string $property, $value);
}
