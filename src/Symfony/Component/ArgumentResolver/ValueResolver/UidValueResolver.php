<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ArgumentResolver\ValueResolver;

use Symfony\Component\ArgumentResolver\ArgumentMetadata\ArgumentMetadata;
use Symfony\Component\ArgumentResolver\Exception\InvalidSourceValueException;
use Symfony\Component\ArgumentResolver\SourceValue;
use Symfony\Component\Uid\AbstractUid;

/**
 * @author Robin Chalas <robin@baksla.sh>
 */
final readonly class UidValueResolver implements ValueResolverInterface
{
    public function resolveArgument(ArgumentMetadata $argument, SourceValue $value): iterable
    {
        $value = $value->get();

        if ($argument->isVariadic()
            || SourceValue::NOT_FOUND === $value
            || !\is_string($value)
            || null === ($uidClass = $argument->getType())
            || !is_subclass_of($uidClass, AbstractUid::class, true)
        ) {
            return [];
        }

        try {
            return [$uidClass::fromString($value)];
        } catch (\InvalidArgumentException $e) {
            throw new InvalidSourceValueException(\sprintf('The uid for the "%s" parameter is invalid.', $argument->getName()), $e->getCode(), $e);
        }
    }
}
