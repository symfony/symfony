<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mapper;

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
     * @param object                 $object The object to map from
     * @param T|class-string<T>|null $to     The object or class to map to
     *
     * @return T
     *
     * @throw RuntimeException|\ReflectionException
     */
    public function map(object $object, object|string $to = null): object;
}
