<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\PrototypeAutoconfigure;

class AutoconfiguredService implements AutoconfiguredInterface
{
    public array $calls = [];

    public function addCall(string $value): void
    {
        $this->calls[] = $value;
    }
}
