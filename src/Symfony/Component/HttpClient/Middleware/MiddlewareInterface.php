<?php

declare(strict_types=1);

namespace Symfony\Component\HttpClient\Middleware;

use Symfony\Contracts\HttpClient\ResponseInterface;

interface MiddlewareInterface
{
    public function __invoke(string $method, string $url, array $options, callable $next): ResponseInterface;
}