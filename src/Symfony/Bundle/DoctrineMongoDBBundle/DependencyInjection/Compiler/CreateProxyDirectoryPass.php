<?php

namespace Symfony\Bundle\DoctrineMongoDBBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

class CreateProxyDirectoryPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasParameter('doctrine.odm.mongodb.proxy_dir')) {
            return;
        }
        // Don't do anything if auto_generate_proxy_classes is false
        if (!$container->getParameter('doctrine.odm.mongodb.auto_generate_proxy_classes')) {
            return;
        }
        // Create document proxy directory
        $proxyCacheDir = $container->getParameter('doctrine.odm.mongodb.proxy_dir');
        if (!is_dir($proxyCacheDir)) {
            if (false === @mkdir($proxyCacheDir, 0777, true)) {
                exit(sprintf('Unable to create the Doctrine Proxy directory (%s)', dirname($proxyCacheDir)));
            }
        } elseif (!is_writable($proxyCacheDir)) {
            exit(sprintf('Unable to write in the Doctrine Proxy directory (%s)', $proxyCacheDir));
        }
    }

}
