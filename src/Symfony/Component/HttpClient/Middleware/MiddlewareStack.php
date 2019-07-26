<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\HttpClient\Middleware;

class MiddlewareStack
{
    /**
     * @var MiddlewareInterface[]
     */
    private $middlewares;

    public function __construct(MiddlewareInterface ...$middlewares)
    {
        $this->middlewares = $middlewares;
    }

    /**
     * Build layers around the core function to implement a middleware mechanism.
     *
     * @param callable $core
     * @param callable $builder
     *
     * @return callable
     */
    public function build(callable $core, callable $builder): callable
    {
        return array_reduce($this->middlewares, $builder, $core);
    }
}
