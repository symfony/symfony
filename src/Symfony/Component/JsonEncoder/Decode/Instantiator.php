<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Decode;

use Symfony\Component\JsonEncoder\Exception\UnexpectedValueException;

/**
 * Instantiates a new $className eagerly, then sets the given properties.
 *
 * The $className class must have a constructor without any parameter
 * and the related properties must be public.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class Instantiator
{
    /**
     * @template T of object
     *
     * @param class-string<T>      $className
     * @param array<string, mixed> $properties
     *
     * @return T
     */
    public function instantiate(string $className, array $properties): object
    {
        $object = new $className();

        foreach ($properties as $name => $value) {
            try {
                $object->{$name} = $value;
            } catch (\TypeError $e) {
                throw new UnexpectedValueException($e->getMessage(), previous: $e);
            }
        }

        return $object;
    }
}
