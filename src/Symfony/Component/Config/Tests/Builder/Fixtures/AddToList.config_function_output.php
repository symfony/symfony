<?php

namespace Symfony\Config;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Config\AddToListConfig;

/**
 * This class is automatically generated to help in creating a config.
 */
class Config 
{
    use \Symfony\Component\DependencyInjection\Resource\ImportsTrait;
    use \Symfony\Component\DependencyInjection\Resource\ParametersTrait;
    use \Symfony\Component\DependencyInjection\Resource\ServicesTrait;
    use \Symfony\Config\AddToListTrait;
    private $builders;
    private $addToListConfig;
    public function __construct(public readonly ?string $env)
    {
        $this->addToListConfig = new AddToListConfig();
        $this->builders = [$this->addToListConfig];
    }
    public function getBuilders(): array
    {
        return $this->builders;
    }

}
