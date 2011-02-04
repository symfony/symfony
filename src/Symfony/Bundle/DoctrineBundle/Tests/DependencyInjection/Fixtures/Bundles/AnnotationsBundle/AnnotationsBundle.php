<?php

namespace Fixtures\Bundles\AnnotationsBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class AnnotationsBundle extends Bundle
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