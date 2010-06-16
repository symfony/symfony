<?php

namespace Symfony\Framework\ProfilerBundle\DataCollector;

use Symfony\Components\DependencyInjection\ContainerInterface;
use Symfony\Framework\ProfilerBundle\Profiler;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * DataCollector.
 *
 * @package    Symfony
 * @subpackage Framework_ProfilerBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class DataCollector implements DataCollectorInterface
{
    protected $profiler;
    protected $container;
    protected $data;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getData()
    {
        return $this->data;
    }

    public function setData($data)
    {
        $this->data = $data;
    }

    abstract public function collect();

    public function setProfiler(Profiler $profiler)
    {
        $this->profiler = $profiler;
    }
}
