<?php

namespace Symfony\Config;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Config\ScalarNormalizedTypesConfig;

/**
 * This class is automatically generated to help in creating a config.
 */
class Config 
{
    use \Symfony\Component\DependencyInjection\Resource\ImportsTrait;
    use \Symfony\Component\DependencyInjection\Resource\ParametersTrait;
    use \Symfony\Component\DependencyInjection\Resource\ServicesTrait;
    use \Symfony\Config\ScalarNormalizedTypesTrait;
    private $builders;
    private $scalarNormalizedTypesConfig;
    public function __construct(public readonly ?string $env)
    {
        $this->scalarNormalizedTypesConfig = new ScalarNormalizedTypesConfig();
        $this->builders = [$this->scalarNormalizedTypesConfig];
    }
    public function getBuilders(): array
    {
        return $this->builders;
    }

}
