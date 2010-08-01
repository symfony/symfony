<?php

namespace Symfony\Framework\Bundle;

use Symfony\Components\DependencyInjection\ContainerInterface;
use Symfony\Components\DependencyInjection\ContainerBuilder;
use Symfony\Components\DependencyInjection\ParameterBag\ParameterBagInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * BundleInterface.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface BundleInterface
{
    /**
     * Customizes the Container instance.
     *
     * @param ParameterBagInterface $parameterBag A ParameterBagInterface instance
     *
     * @return ContainerBuilder A ContainerBuilder instance
     */
    public function buildContainer(ParameterBagInterface $parameterBag);

    /**
     * Boots the Bundle.
     *
     * @param ContainerInterface $container A ContainerInterface instance
     */
    public function boot(ContainerInterface $container);

    /**
     * Shutdowns the Bundle.
     *
     * @param ContainerInterface $container A ContainerInterface instance
     */
    public function shutdown(ContainerInterface $container);
}
