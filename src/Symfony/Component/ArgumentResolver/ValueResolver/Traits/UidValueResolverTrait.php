<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ArgumentResolver\ValueResolver\Traits;

use Symfony\Component\ArgumentResolver\ArgumentMetadata\ArgumentMetadata;
use Symfony\Component\ArgumentResolver\Exception\InvalidRawValueException;
use Symfony\Component\Uid\AbstractUid;

/**
 * @author Robin Chalas <robin@baksla.sh>
 */
trait UidValueResolverTrait
{
    private function doResolve(ArgumentMetadata $argument, array $rawValues): iterable
    {
        $value = $rawValues[0] ?? null;

        if ($argument->isVariadic()
            || !\is_string($value)
            || null === ($uidClass = $argument->getType())
            || !is_subclass_of($uidClass, AbstractUid::class, true)
        ) {
            return [];
        }

        try {
            return [$uidClass::fromString($value)];
        } catch (\InvalidArgumentException $e) {
            throw new InvalidRawValueException(\sprintf('The uid for the "%s" parameter is invalid.', $argument->getName()), $e);
        }
    }
}
