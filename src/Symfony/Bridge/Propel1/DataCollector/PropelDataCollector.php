<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Propel1\DataCollector;

use Symfony\Bridge\Propel1\Logger\PropelLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * The PropelDataCollector collector class collects information.
 *
 * @author William Durand <william.durand1@gmail.com>
 */
class PropelDataCollector extends DataCollector
{
    /**
     * Propel logger
     *
     * @var \Symfony\Bridge\Propel1\Logger\PropelLogger
     */
    private $logger;

    /**
     * Propel configuration
     *
     * @var \PropelConfiguration
     */
    protected $propelConfiguration;

    /**
     * Constructor
     *
     * @param PropelLogger         $logger              A Propel logger.
     * @param \PropelConfiguration $propelConfiguration The Propel configuration object.
     */
    public function __construct(PropelLogger $logger, \PropelConfiguration $propelConfiguration)
    {
        $this->logger = $logger;
        $this->propelConfiguration = $propelConfiguration;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $this->data = array(
            'queries' => $this->buildQueries(),
            'querycount' => $this->countQueries(),
        );
    }

    /**
     * Returns the collector name.
     *
     * @return string The collector name.
     */
    public function getName()
    {
        return 'propel';
    }

    /**
     * Returns queries.
     *
     * @return array Queries
     */
    public function getQueries()
    {
        return $this->data['queries'];
    }

    /**
     * Returns the query count.
     *
     * @return int The query count
     */
    public function getQueryCount()
    {
        return $this->data['querycount'];
    }

    /**
     * Returns the total time of queries.
     *
     * @return float The total time of queries
     */
    public function getTime()
    {
        $time = 0;
        foreach ($this->data['queries'] as $query) {
            $time += (float) $query['time'];
        }

        return $time;
    }

    /**
     * Creates an array of Build objects.
     *
     * @return array An array of Build objects
     */
    private function buildQueries()
    {
        $queries = array();

        $outerGlue = $this->propelConfiguration->getParameter('debugpdo.logging.outerglue', ' | ');
        $innerGlue = $this->propelConfiguration->getParameter('debugpdo.logging.innerglue', ': ');

        foreach ($this->logger->getQueries() as $q) {
            $parts = explode($outerGlue, $q, 4);

            $times = explode($innerGlue, $parts[0]);
            $con = explode($innerGlue, $parts[2]);
            $memories = explode($innerGlue, $parts[1]);

            $sql = trim($parts[3]);
            $con = trim($con[1]);
            $time = trim($times[1]);
            $memory = trim($memories[1]);

            $queries[] = array('connection' => $con, 'sql' => $sql, 'time' => $time, 'memory' => $memory);
        }

        return $queries;
    }

    /**
     * Count queries.
     *
     * @return int The number of queries.
     */
    private function countQueries()
    {
        return count($this->logger->getQueries());
    }
}
