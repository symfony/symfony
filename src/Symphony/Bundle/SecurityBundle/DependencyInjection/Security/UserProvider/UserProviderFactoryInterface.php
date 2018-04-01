<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\SecurityBundle\DependencyInjection\Security\UserProvider;

use Symphony\Component\Config\Definition\Builder\NodeDefinition;
use Symphony\Component\DependencyInjection\ContainerBuilder;

/**
 * UserProviderFactoryInterface is the interface for all user provider factories.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 * @author Christophe Coevoet <stof@notk.org>
 */
interface UserProviderFactoryInterface
{
    public function create(ContainerBuilder $container, $id, $config);

    public function getKey();

    public function addConfiguration(NodeDefinition $builder);
}
