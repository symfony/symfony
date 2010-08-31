<?php

namespace Symfony\Bundle\DoctrineMongoDBBundle\Logger;

use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Logger for the Doctrine MongoDB ODM.
 * 
 * @author Kris Wallsmith <kris.wallsmith@symfony-project.com>
 */
class DoctrineMongoDBLogger
{
    protected $logger;
    protected $nbQueries;

    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger;
        $this->nbQueries = 0;
    }

    public function logQuery($query)
    {
        ++$this->nbQueries;

        if (null !== $this->logger) {
            $this->logger->info(static::formatQuery($query));
        }
    }

    public function getNbQueries()
    {
        return $this->nbQueries;
    }

    /**
     * Formats the supplied query array recursively.
     * 
     * @param array $query All or part of a query array
     * 
     * @return string A serialized object for the log
     */
    static protected function formatQuery(array $query)
    {
        $parts = array();

        $array = true;
        foreach ($query as $key => $value) {
            if (!is_numeric($key)) {
                $array = false;
            }

            if (is_scalar($value)) {
                $formatted = json_encode($value);
            } elseif (is_array($value)) {
                $formatted = static::formatQuery($value);
            } elseif ($value instanceof \MongoId) {
                $formatted = 'ObjectId('.json_encode((string) $value).')';
            } elseif ($value instanceof \MongoDate) {
                $formatted = 'new Date('.date('r', $value->sec).')';
            } elseif ($value instanceof \MongoRegex) {
                $formatted = 'new RegExp('.json_encode($value->regex).', '.json_encode($value->flags).')';
            } else {
                $formatted = (string) $value;
            }

            $parts[json_encode($key)] = $formatted;
        }

        if (0 == count($parts)) {
            return $array ? '[ ]' : '{ }';
        }

        if ($array) {
            return '[ '.implode(', ', $parts).' ]';
        } else {
            $mapper = function($key, $value)
            {
                return $key.': '.$value;
            };

            return '{ '.implode(', ', array_map($mapper, array_keys($parts), array_values($parts))).' }';
        }
    }
}
