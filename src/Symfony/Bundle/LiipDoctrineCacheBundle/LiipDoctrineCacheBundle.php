<?php

namespace Liip\DoctrineCacheBundle;

use Liip\DoctrineCacheBundle\DependencyInjection\Compiler\ServiceCreationCompilerPass;

use Symfony\Component\DependencyInjection\ContainerBuilder,
    Symfony\Component\HttpKernel\Bundle\Bundle;

class LiipDoctrineCacheBundle extends Bundle
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
