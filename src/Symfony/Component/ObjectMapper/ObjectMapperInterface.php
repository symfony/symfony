<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper;

use Symfony\Component\ObjectMapper\Exception\MappingException;
use Symfony\Component\ObjectMapper\Exception\MappingTransformException;
use Symfony\Component\ObjectMapper\Exception\ReflectionException;

/**
 * Object to object mapper.
 *
 * @template T of object
 *
 * @experimental
 *
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
interface ObjectMapperInterface
{
    /**
     * @param object                 $source The object to map from
     * @param T|class-string<T>|null $target The object or class to map to
     *
     * @return T
     *
     * @throw MappingException|MappingTransformException|ReflectionException
     */
    public function map(object $source, object|string|null $target = null): object;
}
