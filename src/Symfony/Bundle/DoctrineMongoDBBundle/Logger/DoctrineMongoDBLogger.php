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
    const LOG_PREFIX = 'MongoDB query: ';

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
            $this->logger->info(static::LOG_PREFIX.static::formatQuery($query));
        }
    }

    public function getNbQueries()
    {
        return $this->nbQueries;
    }

    public function getQueries()
    {
        $logger = $this->logger->getDebugLogger();

        if (!$logger) {
            return false;
        }

        $offset = strlen(static::LOG_PREFIX);
        $mapper = function($log) use($offset)
        {
            if (0 === strpos($log['message'], DoctrineMongoDBLogger::LOG_PREFIX)) {
                return substr($log['message'], $offset);
            }
        };

        // map queries from logs, remove empty entries and re-index the array
        return array_values(array_filter(array_map($mapper, $logger->getLogs())));
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

            if (is_bool($value)) {
                $formatted = $value ? 'true' : 'false';
            } elseif (is_scalar($value)) {
                $formatted = '"'.$value.'"';
            } elseif (is_array($value)) {
                $formatted = static::formatQuery($value);
            } elseif ($value instanceof \MongoId) {
                $formatted = 'ObjectId("'.$value.'")';
            } elseif ($value instanceof \MongoDate) {
                $formatted = 'new Date("'.date('r', $value->sec).'")';
            } elseif ($value instanceof \DateTime) {
                $formatted = 'new Date("'.date('r', $value->getTimestamp()).'")';
            } elseif ($value instanceof \MongoRegex) {
                $formatted = 'new RegExp("'.$value->regex.'", "'.$value->flags.'")';
            } elseif ($value instanceof \MongoMinKey) {
                $formatted = 'new MinKey()';
            } elseif ($value instanceof \MongoMaxKey) {
                $formatted = 'new MaxKey()';
            } elseif ($value instanceof \MongoBinData) {
                $formatted = 'new BinData("'.$formatted->bin.'", "'.$formatted->type.'")';
            } else {
                $formatted = (string) $value;
            }

            $parts['"'.$key.'"'] = $formatted;
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
