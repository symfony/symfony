<?php

namespace Symfony\Bundle\DoctrineBundle\DataCollector;

use Symfony\Component\HttpKernel\Profiler\DataCollector\DataCollector;
use Symfony\Component\DependencyInjection\ContainerInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * DoctrineDataCollector.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class DoctrineDataCollector extends DataCollector
{
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function collect()
    {
        $this->data = array();
        if ($this->container->has('doctrine.dbal.logger')) {
            $this->data = array(
                'queries' => $this->container->getDoctrine_Dbal_LoggerService()->queries,
            );
        }
    }

    public function getQueryCount()
    {
        return count($this->data['queries']);
    }

    public function getQueries()
    {
        return $this->data['queries'];
    }

    public function getSummary()
    {
        $queries = count($this->data['queries']);
        $queriesColor = $queries < 10 ? '#2d2' : '#d22';

        return sprintf('<img style="margin-left: 10px; vertical-align: middle" alt="" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAKlJREFUeNrsk0EOgyAQRT9KiLog7D0Ql+B4HsULeAHXQFwaiCGBCm1Nmi4a69a/IDNk5g+8ZEhKCcMwYFfCORGlFOgrSVJKNE0DxhgofV6HELBtG5xz8N6XuK7rUjOOYx5I3gbQWoNzDiEEuq5DjLE0LcsCYwystVjXFW3bou/74xkVLuqywfGFaZp+T6uqwmGe52+DPyB+GtwQb4h5q3aI6SREko+HAAMADJ+V5b1xqucAAAAASUVORK5CYII=" />
            <span style="color: %s">%d</span>
        ', $queriesColor, $queries);
    }

    public function getName()
    {
        return 'db';
    }
}
