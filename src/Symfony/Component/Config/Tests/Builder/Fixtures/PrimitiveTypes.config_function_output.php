<?php

namespace Symfony\Config;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Config\PrimitiveTypesConfig;

/**
 * This class is automatically generated to help in creating a config.
 */
class Config 
{
    use \Symfony\Component\DependencyInjection\Resource\ImportsTrait;
    use \Symfony\Component\DependencyInjection\Resource\ParametersTrait;
    use \Symfony\Component\DependencyInjection\Resource\ServicesTrait;
    use \Symfony\Config\PrimitiveTypesTrait;
    private $builders;
    private $primitiveTypesConfig;
    public function __construct(public readonly ?string $env)
    {
        $this->primitiveTypesConfig = new PrimitiveTypesConfig();
        $this->builders = [$this->primitiveTypesConfig];
    }
    public function getBuilders(): array
    {
        return $this->builders;
    }

}
