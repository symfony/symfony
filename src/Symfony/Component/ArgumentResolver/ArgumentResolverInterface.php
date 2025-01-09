<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ArgumentResolver;

/**
 * An ArgumentResolverInterface instance knows how to determine the
 * arguments for a specific function.
 *
 * @author Robin Chalas <robin@baksla.sh>
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface ArgumentResolverInterface
{
    /**
     * Returns the arguments to pass to the callable.
     *
     * @param mixed $input The source from which raw values should be found e.g. an HTTP request
     *
     * @throws \RuntimeException When no value could be provided for a required argument
     */
    public function getArguments(mixed $input, callable $callable, ?\ReflectionFunctionAbstract $reflector = null): array;
}
