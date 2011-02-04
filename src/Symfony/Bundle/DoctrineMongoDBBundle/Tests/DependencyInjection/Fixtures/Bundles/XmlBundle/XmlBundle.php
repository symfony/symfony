<?php

namespace DoctrineMongoDBBundle\Tests\DependencyInjection\Fixtures\Bundles\XmlBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class XmlBundle extends Bundle
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
        return __DIR__;
    }
}
