<?php

namespace Symfony\Config;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Config\ArrayExtraKeysConfig;

/**
 * This class is automatically generated to help in creating a config.
 */
class Config 
{
    use \Symfony\Component\DependencyInjection\Resource\ImportsTrait;
    use \Symfony\Component\DependencyInjection\Resource\ParametersTrait;
    use \Symfony\Component\DependencyInjection\Resource\ServicesTrait;
    use \Symfony\Config\ArrayExtraKeysTrait;
    private $builders;
    private $arrayExtraKeysConfig;
    public function __construct(public readonly ?string $env)
    {
        $this->arrayExtraKeysConfig = new ArrayExtraKeysConfig();
        $this->builders = [$this->arrayExtraKeysConfig];
    }
    public function getBuilders(): array
    {
        return $this->builders;
    }

}
