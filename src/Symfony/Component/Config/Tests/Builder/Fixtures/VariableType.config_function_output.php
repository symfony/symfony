<?php

namespace Symfony\Config;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Config\VariableTypeConfig;

/**
 * This class is automatically generated to help in creating a config.
 */
class Config 
{
    use \Symfony\Component\DependencyInjection\Resource\ImportsTrait;
    use \Symfony\Component\DependencyInjection\Resource\ParametersTrait;
    use \Symfony\Component\DependencyInjection\Resource\ServicesTrait;
    use \Symfony\Config\VariableTypeTrait;
    private $builders;
    private $variableTypeConfig;
    public function __construct(public readonly ?string $env)
    {
        $this->variableTypeConfig = new VariableTypeConfig();
        $this->builders = [$this->variableTypeConfig];
    }
    public function getBuilders(): array
    {
        return $this->builders;
    }

}
