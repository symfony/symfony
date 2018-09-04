<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo;

/**
 * Guesses if the property can be mutated.
 *
 * @author Andrey Samusev <andrey_simfi@list.ru>
 */
interface PropertyAccessWritableInterface
{
    /**
     * Is the property writable?
     *
     * @param string $class
     * @param string $property
     * @param array  $context
     *
     * @return bool|null
     */
    public function isWritable($class, $property, array $context = array());
}
