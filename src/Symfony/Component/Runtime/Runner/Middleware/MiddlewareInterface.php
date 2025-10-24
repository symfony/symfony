<?php

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
interface MiddlewareInterface
{
    public function wrap(callable $handler, array $server): void;
}
