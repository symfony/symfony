<?php

namespace Symfony\Bundle\FrameworkBundle;

use Symfony\Framework\Bundle\Bundle;
use Symfony\Components\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Components\DependencyInjection\Loader\Loader;
use Symfony\Components\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Components\DependencyInjection\BuilderConfiguration;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\WebExtension;

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
 * @subpackage Bundle_FrameworkBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class FrameworkBundle extends Bundle
{
    /**
     * Customizes the Container instance.
     *
     * @param \Symfony\Components\DependencyInjection\ParameterBag\ParameterBagInterface $parameterBag A ParameterBagInterface instance
     *
     * @return \Symfony\Components\DependencyInjection\BuilderConfiguration A BuilderConfiguration instance
     */
    public function buildContainer(ParameterBagInterface $parameterBag)
    {
        Loader::registerExtension(new WebExtension($parameterBag->get('kernel.bundle_dirs'), $parameterBag->get('kernel.bundles')));

        $dirs = array('%kernel.root_dir%/views/%%bundle%%/%%controller%%/%%name%%%%format%%.%%renderer%%');
        foreach ($parameterBag->get('kernel.bundle_dirs') as $dir) {
            $dirs[] = $dir.'/%%bundle%%/Resources/views/%%controller%%/%%name%%%%format%%.%%renderer%%';
        }
        $parameterBag->set('templating.loader.filesystem.path', $dirs);

        $configuration = new BuilderConfiguration();
        if ($parameterBag->get('kernel.debug')) {
            $loader = new XmlFileLoader(__DIR__.'/Resources/config');
            $configuration->merge($loader->load('debug.xml'));
        }

        return $configuration;
    }
}
