<?php

namespace Symfony\Bundle\ZendBundle;

use Symfony\Foundation\Bundle\Bundle;
use Symfony\Components\DependencyInjection\ContainerInterface;
use Symfony\Components\DependencyInjection\Reference;
use Symfony\Components\DependencyInjection\Loader\Loader;
use Symfony\Bundle\ZendBundle\DependencyInjection\ZendExtension;

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
 * @subpackage Bundle_ZendBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ZendBundle extends Bundle
{
    /**
     * Customizes the Container instance.
     *
     * @param Symfony\Components\DependencyInjection\ContainerInterface $container A ContainerInterface instance
     *
     * @return Symfony\Components\DependencyInjection\BuilderConfiguration A BuilderConfiguration instance
     */
    public function buildContainer(ContainerInterface $container)
    {
        Loader::registerExtension(new ZendExtension());
    }
}
