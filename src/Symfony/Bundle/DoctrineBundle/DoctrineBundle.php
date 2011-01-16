<?php

namespace Symfony\Bundle\DoctrineBundle;

use Symfony\Bundle\DoctrineBundle\DependencyInjection\Compiler\CreateProxyDirectoryPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Bundle\DoctrineBundle\DependencyInjection\Compiler\RegisterEventListenersAndSubscribersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Bundle.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 */
class DoctrineBundle extends Bundle
{
    public function registerExtensions(ContainerBuilder $container)
    {
        parent::registerExtensions($container);

        $container->addCompilerPass(new RegisterEventListenersAndSubscribersPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION);
        $container->addCompilerPass(new CreateProxyDirectoryPass(), PassConfig::TYPE_BEFORE_REMOVING);
    }
}
