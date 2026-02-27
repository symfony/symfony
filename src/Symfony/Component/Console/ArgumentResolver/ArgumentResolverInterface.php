<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\ArgumentResolver;

use Symfony\Component\Console\ArgumentResolver\Exception\ResolverNotFoundException;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Determines the arguments for a specific Console Command.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
interface ArgumentResolverInterface
{
    /**
     * Returns the arguments to pass to the Console Command after resolution.
     *
     * @throws \RuntimeException         When no value could be provided for a required argument
     * @throws ResolverNotFoundException
     */
    public function getArguments(InputInterface $input, callable $command, ?\ReflectionFunctionAbstract $reflector = null): array;
}
