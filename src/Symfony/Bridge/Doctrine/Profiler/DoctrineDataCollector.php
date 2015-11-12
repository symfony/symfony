<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Profiler;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\DBAL\Logging\DebugStack;
use Doctrine\DBAL\Types\Type;
use Symfony\Component\Profiler\DataCollector\DataCollectorInterface;

/**
 * DoctrineDataCollector.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class DoctrineDataCollector implements DataCollectorInterface
{
    private $registry;
    private $loggers = array();

    public function __construct(ManagerRegistry $registry)
    {
        $this->registry = $registry;
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
    public function getCollectedData()
    {
        $queries = array();
        foreach ($this->loggers as $name => $logger) {
            $queries[$name] = $this->sanitizeQueries($name, $logger->queries);
        }

        return new DoctrineProfileData($queries, $this->registry);
    }

    private function sanitizeQueries($connectionName, array $queries)
    {
        foreach ($queries as $i => $query) {
            $queries[$i] = $this->sanitizeQuery($connectionName, $query);
        }

        return $queries;
    }

    private function sanitizeQuery($connectionName, $query)
    {
        $query['explainable'] = true;
        if (!is_array($query['params'])) {
            $query['params'] = array($query['params']);
        }
        foreach ($query['params'] as $j => $param) {
            if (isset($query['types'][$j])) {
                // Transform the param according to the type
                $type = $query['types'][$j];
                if (is_string($type)) {
                    $type = Type::getType($type);
                }
                if ($type instanceof Type) {
                    $query['types'][$j] = $type->getBindingType();
                    $param = $type->convertToDatabaseValue($param, $this->registry->getConnection($connectionName)->getDatabasePlatform());
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
     *
     * @param mixed $var
     *
     * @return array
     */
    private function sanitizeParam($var)
    {
        if (is_object($var)) {
            return array(sprintf('Object(%s)', get_class($var)), false);
        }

        if (is_array($var)) {
            $a = array();
            $original = true;
            foreach ($var as $k => $v) {
                list($value, $orig) = $this->sanitizeParam($v);
                $original = $original && $orig;
                $a[$k] = $value;
            }

            return array($a, $original);
        }

        if (is_resource($var)) {
            return array(sprintf('Resource(%s)', get_resource_type($var)), false);
        }

        return array($var, true);
    }
}
