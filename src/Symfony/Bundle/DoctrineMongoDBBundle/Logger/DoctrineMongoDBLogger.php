<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineMongoDBBundle\Logger;

use Doctrine\MongoDB\GridFSFile;
use Symfony\Component\HttpKernel\Log\LoggerInterface;

/**
 * Logger for the Doctrine MongoDB ODM.
 *
 * The {@link logQuery()} method is configured as the logger callable in the
 * service container.
 *
 * @author Kris Wallsmith <kris.wallsmith@symfony.com>
 */
class DoctrineMongoDBLogger
{
    protected $logger;

    protected $prefix;
    protected $queries;

    protected $processed;
    protected $formattedQueries;
    protected $nbRealQueries;

    /**
     * Constructor.
     *
     * @param LoggerInterface $logger The Symfony logger
     * @param string          $prefix A prefix for messages sent to the Symfony logger
     */
    public function __construct(LoggerInterface $logger = null, $prefix = 'MongoDB query: ')
    {
        $this->logger = $logger;
        $this->prefix = $prefix;
        $this->queries = array();
        $this->processed = false;
    }

    /**
     * Logs a query.
     *
     * This method is configured as the logger callable in the service
     * container.
     *
     * @param array $query A query log array from Doctrine
     */
    public function logQuery(array $query)
    {
        $this->queries[] = $query;
        $this->processed = false;

        if (null !== $this->logger) {
            $this->logger->info($this->prefix.static::bsonEncode($query));
        }
    }

    /**
     * Returns the number of queries that have been logged.
     *
     * @return integer The number of queries logged
     */
    public function getNbQueries()
    {
        if (!$this->processed) {
            $this->processQueries();
        }

        return $this->nbRealQueries;
    }

    /**
     * Returns a human-readable array of queries logged.
     *
     * @return array An array of queries
     */
    public function getQueries()
    {
        if (!$this->processed) {
            $this->processQueries();
        }

        return $this->formattedQueries;
    }

    /**
     * Groups and formats query arrays.
     *
     * @param array $queries An array of query arrays
     *
     * @return array An array of human-readable queries
     */
    protected function processQueries()
    {
        $this->formattedQueries = array();
        $this->nbRealQueries = 0;

        $grouped = array();
        $ordered = array();
        foreach ($this->queries as $query) {
            if (!isset($query['query']) || !isset($query['fields'])) {
                // no grouping necessary
                $ordered[] = array($query);
                continue;
            }

            $cursor = serialize($query['query']).serialize($query['fields']);

            // append if issued from cursor (currently just "sort")
            if (isset($query['sort'])) {
                unset($query['query'], $query['fields']);
                $grouped[$cursor][count($grouped[$cursor]) - 1][] = $query;
            } else {
                $grouped[$cursor][] = array($query);
                $ordered[] =& $grouped[$cursor][count($grouped[$cursor]) - 1];
            }
        }

        $i = 0;
        $db = '';
        $query = '';
        foreach ($ordered as $logs) {
            foreach ($logs as $log) {
                if (isset($log['db']) && $db != $log['db']) {
                    // for readability
                    $this->formattedQueries[$i++] = 'use '.$log['db'].';';
                    $db = $log['db'];
                }

                if (isset($log['collection'])) {
                    // flush the previous and start a new query
                    if (!empty($query)) {
                        if ('.' == $query[0]) {
                            $query  = 'db'.$query;
                        }

                        $this->formattedQueries[$i++] = $query.';';
                        ++$this->nbRealQueries;
                    }

                    $query = 'db.'.$log['collection'];
                }

                // format the method call
                if (isset($log['authenticate'])) {
                    $query .= '.authenticate()';
                } elseif (isset($log['batchInsert'])) {
                    $query .= '.batchInsert(**'.$log['num'].' item(s)**)';
                } elseif (isset($log['command'])) {
                    $query .= '.command()';
                } elseif (isset($log['count'])) {
                    $query .= '.count(';
                    if ($log['query'] || $log['limit'] || $log['skip']) {
                        $query .= static::bsonEncode($log['query']);
                        if ($log['limit'] || $log['skip']) {
                            $query .= ', '.static::bsonEncode($log['limit']);
                            if ($log['skip']) {
                                $query .= ', '.static::bsonEncode($log['skip']);
                            }
                        }
                    }
                    $query .= ')';
                } elseif (isset($log['createCollection'])) {
                    $query .= '.createCollection()';
                } elseif (isset($log['createDBRef'])) {
                    $query .= '.createDBRef()';
                } elseif (isset($log['deleteIndex'])) {
                    $query .= '.dropIndex('.static::bsonEncode($log['keys']).')';
                } elseif (isset($log['deleteIndexes'])) {
                    $query .= '.dropIndexes()';
                } elseif (isset($log['drop'])) {
                    $query .= '.drop()';
                } elseif (isset($log['dropDatabase'])) {
                    $query .= '.dropDatabase()';
                } elseif (isset($log['ensureIndex'])) {
                    $query .= '.ensureIndex('.static::bsonEncode($log['keys']).', '.static::bsonEncode($log['options']).')';
                } elseif (isset($log['execute'])) {
                    $query .= '.execute()';
                } elseif (isset($log['find'])) {
                    $query .= '.find(';
                    if ($log['query'] || $log['fields']) {
                        $query .= static::bsonEncode($log['query']);
                        if ($log['fields']) {
                            $query .= ', '.static::bsonEncode($log['fields']);
                        }
                    }
                    $query .= ')';
                } elseif (isset($log['findOne'])) {
                    $query .= '.findOne(';
                    if ($log['query'] || $log['fields']) {
                        $query .= static::bsonEncode($log['query']);
                        if ($log['fields']) {
                            $query .= ', '.static::bsonEncode($log['fields']);
                        }
                    }
                    $query .= ')';
                } elseif (isset($log['getDBRef'])) {
                    $query .= '.getDBRef()';
                } elseif (isset($log['group'])) {
                    $query .= '.group('.static::bsonEncode(array(
                        'keys'    => $log['keys'],
                        'initial' => $log['initial'],
                        'reduce'  => $log['reduce'],
                    )).')';
                } elseif (isset($log['insert'])) {
                    $query .= '.insert('.static::bsonEncode($log['document']).')';
                } elseif (isset($log['remove'])) {
                    $query .= '.remove('.static::bsonEncode($log['query']).')';
                } elseif (isset($log['save'])) {
                    $query .= '.save('.static::bsonEncode($log['document']).')';
                } elseif (isset($log['sort'])) {
                    $query .= '.sort('.static::bsonEncode($log['sortFields']).')';
                } elseif (isset($log['update'])) {
                    // todo: include $log['options']
                    $query .= '.update('.static::bsonEncode($log['query']).', '.static::bsonEncode($log['newObj']).')';
                } elseif (isset($log['validate'])) {
                    $query .= '.validate()';
                }
            }
        }

        if (!empty($query)) {
            if ('.' == $query[0]) {
                $query  = 'db'.$query;
            }

            $this->formattedQueries[$i++] = $query.';';
            ++$this->nbRealQueries;
        }
    }

    static protected function bsonEncode($query, $array = true)
    {
        $parts = array();

        foreach ($query as $key => $value) {
            if (!is_numeric($key)) {
                $array = false;
            }

            if (null === $value) {
                $formatted = 'null';
            } elseif (is_bool($value)) {
                $formatted = $value ? 'true' : 'false';
            } elseif (is_numeric($value)) {
                $formatted = $value;
            } elseif (is_scalar($value)) {
                $formatted = '"'.$value.'"';
            } elseif (is_array($value)) {
                $formatted = static::bsonEncode($value);
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
                $formatted = 'new BinData("'.$value->bin.'", "'.$value->type.'")';
            } elseif ($value instanceof \MongoGridFSFile || $value instanceof GridFSFile) {
                $formatted = 'new MongoGridFSFile("'.$value->getFilename().'")';
            } elseif ($value instanceof \stdClass) {
                $formatted = static::bsonEncode((array) $value);
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
