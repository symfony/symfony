<?php

require_once __DIR__.'/../src/autoload.php';

use Symfony\Foundation\Kernel;
use Symfony\Components\DependencyInjection\Loader\XmlFileLoader as ContainerLoader;
use Symfony\Components\Routing\Loader\XmlFileLoader as RoutingLoader;

use Symfony\Foundation\Bundle\KernelBundle;
use Symfony\Framework\FoundationBundle\FoundationBundle;
use Symfony\Framework\ZendBundle\ZendBundle;
use Symfony\Framework\DoctrineBundle\DoctrineBundle;
use Symfony\Framework\SwiftmailerBundle\SwiftmailerBundle;

class {{ class }}Kernel extends Kernel
{
    public function registerRootDir()
    {
        return __DIR__;
    }

    public function registerBundles()
    {
        $bundles = array(
            new KernelBundle(),
            new FoundationBundle(),

            // enable third-party bundles
            new ZendBundle(),
            new DoctrineBundle(),
            new SwiftmailerBundle(),

            // register your bundles here
        );

        if ($this->isDebug()) {
        }

        return $bundles;
    }

    public function registerBundleDirs()
    {
        return array(
            'Application'        => __DIR__.'/../src/Application',
            'Bundle'             => __DIR__.'/../src/Bundle',
            'Symfony\\Framework' => __DIR__.'/../src/vendor/Symfony/src/Symfony/Framework',
        );
    }

    public function registerContainerConfiguration()
    {
        $loader = new ContainerLoader($this->getBundleDirs());

        return $loader->load(__DIR__.'/config/config_'.$this->getEnvironment().'.xml');
    }

    public function registerRoutes()
    {
        $loader = new RoutingLoader($this->getBundleDirs());

        return $loader->load(__DIR__.'/config/routing.xml');
    }
}
