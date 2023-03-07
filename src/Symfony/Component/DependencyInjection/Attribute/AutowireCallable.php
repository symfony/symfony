<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Attribute;

use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Attribute to tell which callable to give to an argument of type Closure.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class AutowireCallable extends Autowire
{
    /**
     * @param bool|class-string $lazy Whether to use lazy-loading for this argument
     */
    public function __construct(
        string|array $callable = null,
        string $service = null,
        string $method = null,
        bool|string $lazy = false,
    ) {
        if (!(null !== $callable xor null !== $service)) {
            throw new LogicException('#[AutowireCallable] attribute must declare exactly one of $callable or $service.');
        }
        if (!(null !== $callable xor null !== $method)) {
            throw new LogicException('#[AutowireCallable] attribute must declare one of $callable or $method.');
        }

        parent::__construct($callable ?? [new Reference($service), $method ?? '__invoke'], lazy: $lazy);
    }
}
