<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Middleware;

use Symfony\Component\Messenger\Exception\NonNullResultException;

/**
 * @author Paul Le Corre <paul@lecorre.me>
 */
class EnforceNullResultMiddleware implements MiddlewareInterface
{
    public function handle($message, callable $next)
    {
        $result = $next($message);
        if (null !== $result) {
            throw new NonNullResultException(sprintf('Non null result for message %s.', \get_class($message)));
        }

        return $result;
    }
}
