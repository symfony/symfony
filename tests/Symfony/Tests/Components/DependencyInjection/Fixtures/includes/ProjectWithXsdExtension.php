<?php

use Symfony\Components\DependencyInjection\BuilderConfiguration;
use Symfony\Components\DependencyInjection\Definition;
use Symfony\Components\DependencyInjection\Loader\LoaderExtension;

class ProjectWithXsdExtension extends ProjectExtension
{
    public function getXsdValidationBasePath()
    {
        return __DIR__.'/schema';
    }

    public function getNamespace()
    {
        return 'http://www.example.com/schema/projectwithxsd';
    }

    public function getAlias()
    {
        return 'projectwithxsd';
    }
}
