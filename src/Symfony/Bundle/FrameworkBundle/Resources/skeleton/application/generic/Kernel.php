<?php

require_once __DIR__.'/../src/autoload.php';

use Symfony\Framework\Kernel;
use Symfony\Components\DependencyInjection\Loader\LoaderInterface;

use Symfony\Framework\KernelBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\ZendBundle\ZendBundle;
use Symfony\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Bundle\SwiftmailerBundle\SwiftmailerBundle;

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
            new FrameworkBundle(),

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
            'Application'     => __DIR__.'/../src/Application',
            'Bundle'          => __DIR__.'/../src/Bundle',
            'Symfony\\Bundle' => __DIR__.'/../src/vendor/Symfony/src/Symfony/Bundle',
        );
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(__DIR__.'/config/config_'.$this->getEnvironment().'.{{ format }}');
    }
}
