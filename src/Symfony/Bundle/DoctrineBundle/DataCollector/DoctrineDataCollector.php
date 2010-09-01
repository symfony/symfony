<?php

namespace Symfony\Bundle\DoctrineBundle\DataCollector;

use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\DoctrineBundle\Logger\DbalLogger;

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
    protected $logger;

    public function __construct(DbalLogger $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $this->data = array(
            'queries' => null !== $this->logger ? $this->logger->queries : array(),
        );
    }

    public function getQueryCount()
    {
        return count($this->data['queries']);
    }

    public function getQueries()
    {
        return $this->data['queries'];
    }

    public function getTime()
    {
        $time = 0;
        foreach ($this->data['queries'] as $query) {
            $time += $query['executionMS'];
        }

        return $time;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'db';
    }
}
