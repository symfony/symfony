<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\DataCollector;

use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bridge\Doctrine\Logger\DbalLogger;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * DoctrineDataCollector.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class DoctrineDataCollector extends DataCollector
{
    private $connections;
    private $managers;
    private $logger;

    public function __construct(RegistryInterface $registry, DbalLogger $logger = null)
    {
        $this->connections = $registry->getConnectionNames();
        $this->managers = $registry->getEntityManagerNames();
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $this->data = array(
            'queries'     => null !== $this->logger ? $this->sanitizeQueries($this->logger->queries) : array(),
            'connections' => $this->connections,
            'managers'    => $this->managers,
        );
    }

    public function getManagers()
    {
        return $this->data['managers'];
    }

    public function getConnections()
    {
        return $this->data['connections'];
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

    private function sanitizeQueries($queries)
    {
        foreach ($queries as $i => $query) {
            foreach ((array) $query['params'] as $j => $param) {
                $queries[$i]['params'][$j] = $this->varToString($param);
            }
        }

        return $queries;
    }
}
