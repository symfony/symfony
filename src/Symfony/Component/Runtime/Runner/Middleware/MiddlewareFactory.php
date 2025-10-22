<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Runtime\Runner\Middleware;

/**
 * @author Sascha Heilmeier <sascha.heilmeier@netlogix.de>
 */
readonly class MiddlewareFactory
{
    /**
     * @param class-string<MiddlewareInterface> ...$middleware
     * @return \Generator<MiddlewareInterface>
     */
    public function create(string ...$middleware): \Generator
    {
        foreach ($middleware as $middlewareClass) {
            yield new $middlewareClass();
        }
    }
}
