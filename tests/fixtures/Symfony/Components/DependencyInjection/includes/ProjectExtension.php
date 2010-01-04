<?php

use Symfony\Components\DependencyInjection\BuilderConfiguration;
use Symfony\Components\DependencyInjection\Definition;
use Symfony\Components\DependencyInjection\Loader\LoaderExtension;

class ProjectExtension extends LoaderExtension
{
  public function barLoad(array $config)
  {
    $configuration = new BuilderConfiguration();

    $configuration->setDefinition('project.service.bar', new Definition('FooClass'));
    $configuration->setParameter('project.parameter.bar', isset($config['foo']) ? $config['foo'] : 'foobar');

    return $configuration;
  }

  public function getNamespace()
  {
    return 'project';
  }
}
