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
 * Guesses if the property can be accessed.
 *
 * @author Andrey Samusev <andrey_simfi@list.ru>
 */
interface PropertyAccessReadableInterface
{
    /**
     * Is the property readable?
     *
     * @param string $class
     * @param string $property
     * @param array  $context
     *
     * @return bool|null
     */
    public function isReadable($class, $property, array $context = array());
}
