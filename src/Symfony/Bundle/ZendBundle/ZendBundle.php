<?php

namespace Symfony\Bundle\ZendBundle;

use Symfony\Framework\Bundle\Bundle;
use Symfony\Components\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Components\DependencyInjection\Reference;
use Symfony\Components\DependencyInjection\ContainerBuilder;
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
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ZendBundle extends Bundle
{
    /**
     * Customizes the Container instance.
     *
     * @param ParameterBagInterface $parameterBag A ParameterBagInterface instance
     *
     * @return ContainerBuilder A ContainerBuilder instance
     */
    public function buildContainer(ParameterBagInterface $parameterBag)
    {
        ContainerBuilder::registerExtension(new ZendExtension());
    }
}
