<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineMongoDBBundle;

use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Bundle\DoctrineMongoDBBundle\DependencyInjection\Compiler\CreateHydratorDirectoryPass;
use Symfony\Bundle\DoctrineMongoDBBundle\DependencyInjection\Compiler\CreateProxyDirectoryPass;
use Symfony\Bundle\DoctrineMongoDBBundle\DependencyInjection\Compiler\RegisterEventListenersAndSubscribersPass;

/**
 * Doctrine MongoDB ODM bundle.
 *
 * @author Bulat Shakirzyanov <bulat@theopenskyproject.com>
 * @author Kris Wallsmith <kris.wallsmith@symfony.com>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 */
class DoctrineMongoDBBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new RegisterEventListenersAndSubscribersPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION);
        $container->addCompilerPass(new CreateProxyDirectoryPass(), PassConfig::TYPE_BEFORE_REMOVING);
        $container->addCompilerPass(new CreateHydratorDirectoryPass(), PassConfig::TYPE_BEFORE_REMOVING);
    }
}
