<?php

class ProjectWithXsdExtension extends ProjectExtension
{
    /**
     * @deprecated since Symfony 7.4, to be removed in Symfony 8.0 together with XML support.
     */
    public function getXsdValidationBasePath(): string
    {
        return __DIR__.'/schema';
    }

    /**
     * @deprecated since Symfony 7.4, to be removed in Symfony 8.0 together with XML support.
     */
    public function getNamespace(): string
    {
        return 'http://www.example.com/schema/projectwithxsd';
    }

    public function getAlias(): string
    {
        return 'projectwithxsd';
    }
}
