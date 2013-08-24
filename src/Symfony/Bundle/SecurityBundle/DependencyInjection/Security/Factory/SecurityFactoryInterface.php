<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * SecurityFactoryInterface is the interface for all security authentication listener.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface SecurityFactoryInterface
{
    /**
     * @since v2.1.0
     */
    public function create(ContainerBuilder $container, $id, $config, $userProvider, $defaultEntryPoint);

    /**
     * @since v2.1.0
     */
    public function getPosition();

    /**
     * @since v2.1.0
     */
    public function getKey();

    /**
     * @since v2.1.0
     */
    public function addConfiguration(NodeDefinition $builder);
}
