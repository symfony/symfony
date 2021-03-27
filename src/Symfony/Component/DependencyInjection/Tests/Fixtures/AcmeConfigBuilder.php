<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Symfony\Component\Config\Builder\ConfigBuilderInterface;

class AcmeConfigBuilder implements ConfigBuilderInterface
{
    private $color;

    public function color($value)
    {
        $this->color = $value;
    }

    public function toArray(): array
    {
        return [
            'color' => $this->color
        ];
    }

    public function getExtensionAlias(): string
    {
        return 'acme';
    }
}
