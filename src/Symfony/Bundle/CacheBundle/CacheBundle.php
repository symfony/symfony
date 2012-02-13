<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace Symfony\Bundle\CacheBundle;

use Symfony\Bundle\CacheBundle\DependencyInjection\Compiler\ServiceCreationCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Bundle\CacheBundle\DependencyInjection\CacheExtension;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Bundle\CacheBundle\DependencyInjection\Backend\MemcachedBackendFactory;
use Symfony\Bundle\CacheBundle\DependencyInjection\Provider\MemcachedProviderFactory;
use Symfony\Bundle\CacheBundle\DependencyInjection\Backend\ApcBackendFactory;
use Symfony\Bundle\CacheBundle\DependencyInjection\Provider\ApcProviderFactory;

/**
 * @author Victor Berchet <victor@suumit.com>
 */
class CacheBundle extends Bundle
{
    private $kernel;

   public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $ext = $container->getExtension('cache');

        $ext->addBackendFactory(new MemcachedBackendFactory());
        $ext->addBackendFactory(new ApcBackendFactory());

        $ext->addProviderFactory(new MemcachedProviderFactory());
        //$ext->addProviderFactory(new ApcProviderFactory());


        //$container->addCompilerPass(new ServiceCreationCompilerPass());
    }
}
