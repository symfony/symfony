<?php

namespace Symfony\Bundle\CacheBundle;

use Symfony\Bundle\CacheBundle\DependencyInjection\Compiler\ServiceCreationCompilerPass;

use Symfony\Component\DependencyInjection\ContainerBuilder,
    Symfony\Component\HttpKernel\Bundle\Bundle;

class CacheBundle extends Bundle
{
    /**
     * @see Symfony\Component\HttpKernel\Bundle.Bundle::build()
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new ServiceCreationCompilerPass());
    }
}
