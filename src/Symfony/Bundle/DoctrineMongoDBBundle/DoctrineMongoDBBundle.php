<?php

namespace Symfony\Bundle\DoctrineMongoDBBundle;

use Symfony\Framework\Bundle\Bundle;
use Symfony\Components\DependencyInjection\ContainerInterface;
use Symfony\Components\DependencyInjection\Loader\Loader;
use Symfony\Bundle\DoctrineMongoDBBundle\DependencyInjection\MongoDBExtension;

/**
 * Doctrine MongoDB ODM bundle.
 *
 * @author Bulat Shakirzyanov <bulat@theopenskyproject.com>
 * @author Kris Wallsmith <kris.wallsmith@symfony-project.com>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 */
class DoctrineMongoDBBundle extends Bundle 
{
    public function buildContainer(ContainerInterface $container) 
    {
        Loader::registerExtension(new MongoDBExtension($container->getParameter('kernel.bundles')));
    }
}