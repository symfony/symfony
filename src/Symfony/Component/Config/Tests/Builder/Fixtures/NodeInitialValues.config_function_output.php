<?php

namespace Symfony\Config;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Config\NodeInitialValuesConfig;

/**
 * This class is automatically generated to help in creating a config.
 */
class Config 
{
    use \Symfony\Component\DependencyInjection\Resource\ImportsTrait;
    use \Symfony\Component\DependencyInjection\Resource\ParametersTrait;
    use \Symfony\Component\DependencyInjection\Resource\ServicesTrait;
    use \Symfony\Config\NodeInitialValuesTrait;
    private $builders;
    private $nodeInitialValuesConfig;
    public function __construct(public readonly ?string $env)
    {
        $this->nodeInitialValuesConfig = new NodeInitialValuesConfig();
        $this->builders = [$this->nodeInitialValuesConfig];
    }
    public function getBuilders(): array
    {
        return $this->builders;
    }

}
