<?php

namespace DoctrineMongoDBBundle\Tests\DependencyInjection\Fixtures\Bundles\YamlBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class YamlBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function getNamespace()
    {
        return __NAMESPACE__;
    }

    /**
     * {@inheritdoc}
     */
    protected function getPath()
    {
        return __DIR__;
    }
}
