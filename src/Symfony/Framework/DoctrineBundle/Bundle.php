<?php

namespace Symfony\Framework\DoctrineBundle;

use Symfony\Foundation\Bundle\Bundle as BaseBundle;
use Symfony\Components\DependencyInjection\ContainerInterface;
use Symfony\Components\DependencyInjection\Loader\Loader;
use Symfony\Components\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Framework\DoctrineBundle\DependencyInjection\DoctrineExtension;

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
 * @author     Jonathan H. Wage <jonwage@gmail.com>
 */
class Bundle extends BaseBundle
{
  public function buildContainer(ContainerInterface $container)
  {
    Loader::registerExtension(new DoctrineExtension($container->getParameter('kernel.bundle_dirs'), $container->getParameter('kernel.bundles')));

    $metadataDirs = array();
    $entityDirs = array();
    $bundleDirs = $container->getParameter('kernel.bundle_dirs');
    foreach ($container->getParameter('kernel.bundles') as $className)
    {
      $tmp = dirname(str_replace('\\', '/', $className));
      $namespace = str_replace('/', '\\', dirname($tmp));
      $class = basename($tmp);

      if (isset($bundleDirs[$namespace]))
      {
        if (is_dir($dir = $bundleDirs[$namespace].'/'.$class.'/Resources/config/doctrine/metadata'))
        {
          $metadataDirs[] = $dir;
        }
        if (is_dir($dir = $bundleDirs[$namespace].'/'.$class.'/Entities'))
        {
          $entityDirs[] = $dir;
        }
      }
    }
    $container->setParameter('doctrine.orm.metadata_driver.mapping_dirs', $metadataDirs);
    $container->setParameter('doctrine.orm.entity_dirs', $entityDirs);
  }
}
