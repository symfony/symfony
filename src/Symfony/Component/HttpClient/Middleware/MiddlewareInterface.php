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

use Symfony\Contracts\HttpClient\ResponseInterface;

interface MiddlewareInterface
{
    public function __invoke(string $method, string $url, array $options, callable $next): ResponseInterface;
}
