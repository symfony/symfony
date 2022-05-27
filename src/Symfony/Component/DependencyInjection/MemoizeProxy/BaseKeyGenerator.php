<?php

namespace Symfony\Component\DependencyInjection\MemoizeProxy;

class BaseKeyGenerator implements KeyGeneratorInterface
{
    /**
     * @inheritDoc
     */
    public function __invoke(string $className, string $method, array $arguments): string
    {
        return hash('sha256', $className.$method.serialize($arguments));
    }
}
