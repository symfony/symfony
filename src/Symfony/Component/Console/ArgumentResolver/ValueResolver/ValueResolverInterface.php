<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\ArgumentResolver\ValueResolver;

use Symfony\Component\Console\Attribute\Reflection\ReflectionMember;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Resolves the value of a command argument from its parameter metadata and the console input.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
interface ValueResolverInterface
{
    /**
     * Returns the possible value(s) for the argument.
     */
    public function resolve(string $argumentName, InputInterface $input, ReflectionMember $member): iterable;
}
