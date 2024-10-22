<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Decorator;

/**
 * Decorates the functionality of a given function.
 *
 * @author Yonel Ceruto <open@yceruto.dev>
 *
 * @experimental
 */
interface DecoratorInterface
{
    public function decorate(\Closure $func): \Closure;
}
