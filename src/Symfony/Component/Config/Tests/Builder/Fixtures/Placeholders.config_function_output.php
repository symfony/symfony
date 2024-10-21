<?php

namespace Symfony\Config;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Config\PlaceholdersConfig;

/**
 * This class is automatically generated to help in creating a config.
 */
class Config 
{
    use \Symfony\Component\DependencyInjection\Resource\ImportsTrait;
    use \Symfony\Component\DependencyInjection\Resource\ParametersTrait;
    use \Symfony\Component\DependencyInjection\Resource\ServicesTrait;
    use \Symfony\Config\PlaceholdersTrait;
    private $builders;
    private $placeholdersConfig;
    public function __construct(public readonly ?string $env)
    {
        $this->placeholdersConfig = new PlaceholdersConfig();
        $this->builders = [$this->placeholdersConfig];
    }
    public function getBuilders(): array
    {
        return $this->builders;
    }

}
