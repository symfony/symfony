<?php

namespace Symfony\Foundation\Bundle;

use Symfony\Foundation\Bundle\Bundle;
use Symfony\Foundation\ClassCollectionLoader;
use Symfony\Components\DependencyInjection\ContainerInterface;
use Symfony\Components\DependencyInjection\Container;
use Symfony\Components\DependencyInjection\Loader\Loader;
use Symfony\Components\Debug\ErrorHandler;

/*
 * This file is part of the symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * 
 *
 * @package    symfony
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class KernelBundle extends Bundle
{
  public function buildContainer(ContainerInterface $container)
  {
    Loader::registerExtension(new KernelExtension());
  }

  public function boot(ContainerInterface $container)
  {
    $container->getErrorHandlerService();

    // load core classes
    ClassCollectionLoader::load($container->getParameter('kernel.compiled_classes'), $container->getParameter('kernel.cache_dir'), 'classes', $container->getParameter('kernel.debug'));
  }
}
