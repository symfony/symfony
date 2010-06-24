<?php

namespace Symfony\Framework\DoctrineMongoDBBundle;

use Symfony\Foundation\Bundle\Bundle;
use Symfony\Components\DependencyInjection\ContainerInterface;
use Symfony\Components\DependencyInjection\Loader\Loader;
use Symfony\Framework\DoctrineMongoDBBundle\DependencyInjection\MongoDBExtension;

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