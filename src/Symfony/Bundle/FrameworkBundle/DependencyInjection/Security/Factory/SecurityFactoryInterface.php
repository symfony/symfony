<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Security\Factory;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * SecurityFactoryInterface is the interface for all security authentication listener.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface SecurityFactoryInterface
{
    function create(ContainerBuilder $container, $id, $config, $userProvider, $defaultEntryPoint);

    function getPosition();

    function getKey();
}
