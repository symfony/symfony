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

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\DBAL\Logging\DebugStack;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * DoctrineDataCollector.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class DoctrineDataCollector extends DataCollector
{
    private $registry;
    private $connections;
    private $managers;

    /**
     * @var DebugStack[]
     */
    private $loggers = [];

    public function __construct(ManagerRegistry $registry)
    {
        $this->registry = $registry;
        $this->connections = $registry->getConnectionNames();
        $this->managers = $registry->getManagerNames();
    }

    /**
     * Adds the stack logger for a connection.
     *
     * @param string     $name
     * @param DebugStack $logger
     */
    public function addLogger($name, DebugStack $logger)
    {
        $this->loggers[$name] = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $queries = [];
        foreach ($this->loggers as $name => $logger) {
            $queries[$name] = $this->sanitizeQueries($name, $logger->queries);
        }

        $this->data = [
            'queries' => $queries,
            'connections' => $this->connections,
            'managers' => $this->managers,
        ];
    }

    public function reset()
    {
        $this->data = [];

        foreach ($this->loggers as $logger) {
            $logger->queries = [];
            $logger->currentQuery = 0;
        }
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
        return array_sum(array_map('count', $this->data['queries']));
    }

    public function getQueries()
    {
        return $this->data['queries'];
    }

    public function getTime()
    {
        $time = 0;
        foreach ($this->data['queries'] as $queries) {
            foreach ($queries as $query) {
                $time += $query['executionMS'];
            }
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

    private function sanitizeQueries(string $connectionName, array $queries)
    {
        foreach ($queries as $i => $query) {
            $queries[$i] = $this->sanitizeQuery($connectionName, $query);
        }

        return $queries;
    }

    private function sanitizeQuery(string $connectionName, $query)
    {
        $query['explainable'] = true;
        if (null === $query['params']) {
            $query['params'] = [];
        }
        if (!\is_array($query['params'])) {
            $query['params'] = [$query['params']];
        }
        if (!\is_array($query['types'])) {
            $query['types'] = [];
        }
        foreach ($query['params'] as $j => $param) {
            if (isset($query['types'][$j])) {
                // Transform the param according to the type
                $type = $query['types'][$j];
                if (\is_string($type)) {
                    $type = Type::getType($type);
                }
                if ($type instanceof Type) {
                    $query['types'][$j] = $type->getBindingType();
                    try {
                        $param = $type->convertToDatabaseValue($param, $this->registry->getConnection($connectionName)->getDatabasePlatform());
                    } catch (\TypeError $e) {
                        // Error thrown while processing params, query is not explainable.
                        $query['explainable'] = false;
                    } catch (ConversionException $e) {
                        $query['explainable'] = false;
                    }
                }
            }

            list($query['params'][$j], $explainable) = $this->sanitizeParam($param);
            if (!$explainable) {
                $query['explainable'] = false;
            }
        }

        return $query;
    }

    /**
     * Sanitizes a param.
     *
     * The return value is an array with the sanitized value and a boolean
     * indicating if the original value was kept (allowing to use the sanitized
     * value to explain the query).
     */
    private function sanitizeParam($var): array
    {
        if (\is_object($var)) {
            $className = \get_class($var);

            return method_exists($var, '__toString') ?
                [sprintf('/* Object(%s): */"%s"', $className, $var->__toString()), false] :
                [sprintf('/* Object(%s) */', $className), false];
        }

        if (\is_array($var)) {
            $a = [];
            $original = true;
            foreach ($var as $k => $v) {
                list($value, $orig) = $this->sanitizeParam($v);
                $original = $original && $orig;
                $a[$k] = $value;
            }

            return [$a, $original];
        }

        if (\is_resource($var)) {
            return [sprintf('/* Resource(%s) */', get_resource_type($var)), false];
        }

        return [$var, true];
    }
}
