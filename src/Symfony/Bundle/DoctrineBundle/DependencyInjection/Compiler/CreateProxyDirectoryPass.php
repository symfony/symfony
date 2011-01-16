<?php

namespace Symfony\Bundle\DoctrineBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

class CreateProxyDirectoryPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasParameter('doctrine.orm.proxy_dir')) {
            return;
        }
        // Don't do anything if auto_generate_proxy_classes is false
        if (!$container->getParameter('doctrine.orm.auto_generate_proxy_classes')) {
            return;
        }
        $proxyCacheDir = $container->getParameter('doctrine.orm.proxy_dir');
        // Create entity proxy directory
        if (!is_dir($proxyCacheDir)) {
            if (false === @mkdir($proxyCacheDir, 0777, true)) {
                throw new \RuntimeException(sprintf('Unable to create the Doctrine Proxy directory (%s)', dirname($proxyCacheDir)));
            }
        } elseif (!is_writable($proxyCacheDir) && $container->getParameter('doctrine.orm.auto_generate_proxy_classes') == true) {
            throw new \RuntimeException(sprintf('Unable to write in the Doctrine Proxy directory (%s)', $proxyCacheDir));
        }
    }
}
