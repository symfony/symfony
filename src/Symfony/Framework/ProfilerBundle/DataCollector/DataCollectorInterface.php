<?php

namespace Symfony\Framework\ProfilerBundle\DataCollector;

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
 * DataCollectorInterface.
 *
 * @package    Symfony
 * @subpackage Framework_ProfilerBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface DataCollectorInterface
{
    public function setProfiler(Profiler $profiler);

    public function getData();

    public function getName();
}
