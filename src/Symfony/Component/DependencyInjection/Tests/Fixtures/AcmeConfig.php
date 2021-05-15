<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Symfony\Component\Config\Builder\ConfigBuilderInterface;

class AcmeConfig implements ConfigBuilderInterface
{
    private $color;

    private $nested;

    public function color($value)
    {
        $this->color = $value;
    }

    public function nested(array $value)
    {
        if (null === $this->nested) {
            $this->nested = new \Symfony\Config\AcmeConfig\NestedConfig();
        } elseif ([] !== $value) {
            throw new \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException(sprintf('The node created by "nested()" has already been initialized. You cannot pass values the second time you call nested().'));
        }

        return $this->nested;
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

class_alias(AcmeConfig::class, '\\Symfony\\Config\\AcmeConfig');
