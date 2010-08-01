<?php

namespace Symfony\Components\HttpKernel\Profiler\DataCollector;

use Symfony\Components\HttpKernel\Profiler\Profiler;

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
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class DataCollector implements DataCollectorInterface
{
    protected $profiler;
    protected $data;

    public function getData()
    {
        return $this->data;
    }

    public function setData($data)
    {
        $this->data = $data;
    }

    public function setProfiler(Profiler $profiler)
    {
        $this->profiler = $profiler;
    }
}
