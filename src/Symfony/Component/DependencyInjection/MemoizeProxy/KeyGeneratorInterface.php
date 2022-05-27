<?php

namespace Symfony\Component\DependencyInjection\MemoizeProxy;

interface KeyGeneratorInterface
{
    /**
     * Generates a cache key for the given arguments.
     */
    public function __invoke(string $className, string $method, array $arguments): string;
}
