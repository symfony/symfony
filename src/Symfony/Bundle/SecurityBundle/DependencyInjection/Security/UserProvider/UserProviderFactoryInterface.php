<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Security\UserProvider;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * UserProviderFactoryInterface is the interface for all user provider factories.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Christophe Coevoet <stof@notk.org>
 */
interface UserProviderFactoryInterface
{
    /**
     * @since v2.1.0
     */
    public function create(ContainerBuilder $container, $id, $config);

    /**
     * @since v2.1.0
     */
    public function getKey();

    /**
     * @since v2.1.0
     */
    public function addConfiguration(NodeDefinition $builder);
}
