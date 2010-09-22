<?php

namespace Symfony\Bundle\FrameworkBundle;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Profiler\Profiler as BaseProfiler;
use Symfony\Component\HttpKernel\Profiler\ProfilerStorageInterface;
use Symfony\Component\HttpKernel\Log\LoggerInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Profiler.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Profiler extends BaseProfiler
{
    public function __construct(ContainerInterface $container, ProfilerStorageInterface $storage, LoggerInterface $logger = null)
    {
        parent::__construct($storage, $logger);

        foreach ($container->findTaggedServiceIds('data_collector') as $id => $attributes) {
            $this->add($container->get($id));
        }
    }
}
