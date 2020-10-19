<?php

class ProjectWithXsdExtension extends ProjectExtension
{
    public function getXsdValidationBasePath()
    {
        return __DIR__.'/schema';
    }

    public function getNamespace(): string
    {
        return 'http://www.example.com/schema/projectwithxsd';
    }

    public function getAlias(): string
    {
        return 'projectwithxsd';
    }
}
