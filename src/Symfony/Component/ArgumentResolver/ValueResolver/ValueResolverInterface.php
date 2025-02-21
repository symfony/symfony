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

use Symfony\Component\ArgumentResolver\Exception\InvalidSourceValueException;
use Symfony\Component\ArgumentResolver\ArgumentMetadata\ArgumentMetadata;
use Symfony\Component\ArgumentResolver\SourceValue;

/**
 * Responsible for resolving the value of an argument based on its metadata and its source value.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Robin Chalas <robin@baksla.sh>
 */
interface ValueResolverInterface
{
    /**
     * Returns the resolved argument value(s).
     */
    public function resolveArgument(ArgumentMetadata $argument, SourceValue $value): iterable;
}
