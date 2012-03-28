<?php

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
