<?php

namespace DoctrineBundle\Tests\DependencyInjection\Fixtures\Bundles\YamlBundle;

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
    public function getPath()
    {
        return strtr(__DIR__, '\\', '/');
    }
}
