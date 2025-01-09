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

use Symfony\Component\ArgumentResolver\Exception\InvalidRawValueException;
use Symfony\Component\ArgumentResolver\ArgumentMetadata\ArgumentMetadata;

/**
 * Responsible for resolving the value of an argument based on its metadata.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Robin Chalas <robin@baksla.sh>
 */
interface ValueResolverInterface
{
    /**
     * Returns the resolved argument value(s).
     */
    public function resolve(ArgumentMetadata $argument): iterable;
}
