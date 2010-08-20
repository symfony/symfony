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
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
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

    /**
     * Dumps the service container.
     *
     * @param  array  $options An array of options
     *
     * @return string The representation of the service container
     *
     * @throws \LogicException When this abstract class is not implemented
     */
    public function dump(array $options = array())
    {
        throw new \LogicException('You must extend this abstract class and implement the dump() method.');
    }
}
