<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache;

use Symfony\Component\Cache\Exception\InvalidArgumentException;

/**
 * @author Alexei Prilipko <palex.fpt@gmail.com>
 */
interface SerializerInterface
{
    /**
     * Generates a storable representation of a value.
     *
     * @param $data mixed
     *
     * @return string|mixed serialized value
     *
     * @throws InvalidArgumentException when $data can not be serialized
     */
    public function serialize($data);

    /**
     * Creates a PHP value from a stored representation.
     *
     * @param string|mixed $serialized the serialized string
     *
     * @return mixed Original value
     */
    public function unserialize($serialized);
}
