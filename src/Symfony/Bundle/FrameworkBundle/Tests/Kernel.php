<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests;

use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\HttpKernel\Util\Filesystem;
use Symfony\Component\ClassLoader\UniversalClassLoader;
use Symfony\Component\Config\Loader\LoaderInterface;

class Kernel extends BaseKernel
{
    public function __construct()
    {
        $this->rootDir = sys_get_temp_dir().'/sf2_'.rand(1, 9999);
        if (!is_dir($this->rootDir)) {
            if (false === @mkdir($this->rootDir)) {
                exit(sprintf('Unable to create a temporary directory (%s)', $this->rootDir));
            }
        } elseif (!is_writable($this->rootDir)) {
            exit(sprintf('Unable to write in a temporary directory (%s)', $this->rootDir));
        }

        parent::__construct('env', true);

        $loader = new UniversalClassLoader();
        $loader->registerNamespaces(array(
            'TestBundle'      => __DIR__.'/Fixtures/',
            'TestApplication' => __DIR__.'/Fixtures/',
        ));
        $loader->register();
    }

    public function __destruct()
    {
        $fs = new Filesystem();
        $fs->remove($this->rootDir);
    }

    public function registerBundles()
    {
        return array(
            new \Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new \TestBundle\Sensio\FooBundle\SensioFooBundle(),
            new \TestBundle\Sensio\Cms\FooBundle\SensioCmsFooBundle(),
            new \TestBundle\FooBundle\FooBundle(),
            new \TestBundle\Fabpot\FooBundle\FabpotFooBundle(),
        );
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(function ($container) {
            $container->setParameter('kernel.compiled_classes', array());
        });
    }
}
