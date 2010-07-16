<?php

namespace Symfony\Bundle\DoctrineBundle;

use Symfony\Framework\Bundle\Bundle;
use Symfony\Components\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Components\DependencyInjection\ContainerBuilder;
use Symfony\Components\DependencyInjection\Loader\Loader;
use Symfony\Components\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Bundle.
 *
 * @package    Symfony
 * @subpackage Bundle_DoctrineBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author     Jonathan H. Wage <jonwage@gmail.com>
 */
class DoctrineBundle extends Bundle
{
    /**
     * Customizes the Container instance.
     *
     * @param \Symfony\Components\DependencyInjection\ParameterBag\ParameterBagInterface $parameterBag A ParameterBagInterface instance
     *
     * @return \Symfony\Components\DependencyInjection\ContainerBuilder A ContainerBuilder instance
     */
    public function buildContainer(ParameterBagInterface $parameterBag)
    {
        ContainerBuilder::registerExtension(new DoctrineExtension($parameterBag->get('kernel.bundle_dirs'), $parameterBag->get('kernel.bundles')));

        $metadataDirs = array();
        $entityDirs = array();
        $bundleDirs = $parameterBag->get('kernel.bundle_dirs');
        foreach ($parameterBag->get('kernel.bundles') as $className) {
            $tmp = dirname(str_replace('\\', '/', $className));
            $namespace = str_replace('/', '\\', dirname($tmp));
            $class = basename($tmp);

            if (isset($bundleDirs[$namespace])) {
                if (is_dir($dir = $bundleDirs[$namespace].'/'.$class.'/Resources/config/doctrine/metadata')) {
                    $metadataDirs[] = realpath($dir);
                }
                if (is_dir($dir = $bundleDirs[$namespace].'/'.$class.'/Entities')) {
                    $entityDirs[] = realpath($dir);
                }
            }
        }
        $parameterBag->set('doctrine.orm.metadata_driver.mapping_dirs', $metadataDirs);
        $parameterBag->set('doctrine.orm.entity_dirs', $entityDirs);
    }
}
