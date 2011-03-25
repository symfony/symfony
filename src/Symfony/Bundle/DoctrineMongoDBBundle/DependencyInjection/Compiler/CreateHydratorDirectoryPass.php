<?php

namespace Symfony\Bundle\DoctrineMongoDBBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

class CreateHydratorDirectoryPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasParameter('doctrine.odm.mongodb.hydrator_dir')) {
            return;
        }
        // Don't do anything if auto_generate_hydrator_classes is false
        if (!$container->getParameter('doctrine.odm.mongodb.auto_generate_hydrator_classes')) {
            return;
        }
        // Create document proxy directory
        $hydratorCacheDir = $container->getParameter('doctrine.odm.mongodb.hydrator_dir');
        if (!is_dir($hydratorCacheDir)) {
            if (false === @mkdir($hydratorCacheDir, 0777, true)) {
                exit(sprintf('Unable to create the Doctrine Hydrator directory (%s)', dirname($hydratorCacheDir)));
            }
        } elseif (!is_writable($hydratorCacheDir)) {
            exit(sprintf('Unable to write in the Doctrine Hydrator directory (%s)', $hydratorCacheDir));
        }
    }

}
