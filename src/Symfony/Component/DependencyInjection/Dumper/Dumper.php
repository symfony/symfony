<?php

namespace Symfony\Component\DependencyInjection\Dumper;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Dumper is the abstract class for all built-in dumpers.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class Dumper implements DumperInterface
{
    protected $container;

    /**
     * Constructor.
     *
     * @param ContainerBuilder $container The service container to dump
     */
    public function __construct(ContainerBuilder $container)
    {
        $this->container = $container;
    }
}
