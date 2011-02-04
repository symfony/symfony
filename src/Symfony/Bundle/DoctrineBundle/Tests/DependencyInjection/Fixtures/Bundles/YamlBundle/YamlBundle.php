<?php

namespace Fixtures\Bundles\YamlBundle;

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
        return __DIR__;
    }
}
