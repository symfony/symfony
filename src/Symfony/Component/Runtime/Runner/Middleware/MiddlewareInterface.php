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
    /**
     * @param callable             $handler The request handler, which should be called to process the request
     * @param array<string, mixed> $server  environment variables coming from DotEnv
     */
    public function handle(callable $handler, array $server): void;
}
