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

namespace Symfony\Component\Runtime\Tests\Support;

use Symfony\Component\Runtime\Runner\Middleware\MiddlewareInterface;

class TestMiddleware implements MiddlewareInterface
{
    public function wrap(callable $handler, array $server): void
    {
        $handler();
    }
}
