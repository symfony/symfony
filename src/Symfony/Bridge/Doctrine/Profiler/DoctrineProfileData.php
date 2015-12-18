<?php

namespace Symfony\Bridge\Doctrine\Profiler;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\SchemaValidator;
use Doctrine\ORM\Version;
use Symfony\Component\Profiler\ProfileData\ProfileDataInterface;

class DoctrineProfileData implements ProfileDataInterface
{
    private $queries;
    private $connections;
    private $managers;
    private $invalidEntityCount;
    private $cacheEnabled = false;
    private $cacheLogEnabled = false;
    private $cacheCounts = array('puts' => 0, 'hits' => 0, 'misses' => 0);
    private $cacheRegions = array('puts' => array(), 'hits' => array(), 'misses' => array());
    private $errors = array();
    private $entities = array();

    public function __construct(array $queries, ManagerRegistry $registry)
    {
        $this->queries = $queries;
        $this->connections = $registry->getConnectionNames();
        $this->managers = $registry->getManagerNames();

        /*
         * @var string
         * @var \Doctrine\ORM\EntityManager
         */
        foreach ($registry->getManagers() as $name => $em) {
            $this->entities[$name] = array();
            /** @var $factory \Doctrine\ORM\Mapping\ClassMetadataFactory */
            $factory = $em->getMetadataFactory();
            $validator = new SchemaValidator($em);

            /** @var $class \Doctrine\ORM\Mapping\ClassMetadataInfo */
            foreach ($factory->getLoadedMetadata() as $class) {
                if (!isset($entities[$name][$class->getName()])) {
                    $classErrors = $validator->validateClass($class);
                    $this->entities[$name][$class->getName()] = $class->getName();

                    if (!empty($classErrors)) {
                        $this->errors[$name][$class->getName()] = $classErrors;
                    }
                }
            }

            if (version_compare(Version::VERSION, '2.5.0-DEV') < 0) {
                continue;
            }

            /** @var $emConfig \Doctrine\ORM\Configuration */
            $emConfig = $em->getConfiguration();
            $slcEnabled = $emConfig->isSecondLevelCacheEnabled();

            if (!$slcEnabled) {
                continue;
            }

            $this->cacheEnabled = true;

            /** @var $cacheConfiguration \Doctrine\ORM\Cache\CacheConfiguration */
            $cacheConfiguration = $emConfig->getSecondLevelCacheConfiguration();
            /** @var $cacheLoggerChain \Doctrine\ORM\Cache\Logging\CacheLoggerChain */
            $cacheLoggerChain = $cacheConfiguration->getCacheLogger();

            if (!$cacheLoggerChain || !$cacheLoggerChain->getLogger('statistics')) {
                continue;
            }

            /** @var $cacheLoggerStats \Doctrine\ORM\Cache\Logging\StatisticsCacheLogger */
            $cacheLoggerStats = $cacheLoggerChain->getLogger('statistics');
            $this->cacheLogEnabled = true;

            $this->cacheCounts['puts'] += $cacheLoggerStats->getPutCount();
            $this->cacheCounts['hits'] += $cacheLoggerStats->getHitCount();
            $this->cacheCounts['misses'] += $cacheLoggerStats->getMissCount();

            foreach ($cacheLoggerStats->getRegionsPut() as $key => $value) {
                if (!isset($this->cacheRegions['hits'][$key])) {
                    $this->cacheRegions['hits'][$key] = 0;
                }

                $this->cacheRegions['puts'][$key] += $value;
            }

            foreach ($cacheLoggerStats->getRegionsHit() as $key => $value) {
                if (!isset($this->cacheRegions['hits'][$key])) {
                    $this->cacheRegions['hits'][$key] = 0;
                }

                $this->cacheRegions['hits'][$key] += $value;
            }

            foreach ($cacheLoggerStats->getRegionsMiss() as $key => $value) {
                if (!isset($this->cacheRegions['misses'][$key])) {
                    $this->cacheRegions['misses'][$key] = 0;
                }

                $this->cacheRegions['misses'][$key] += $value;
            }
        }
    }

    public function getManagers()
    {
        return $this->managers;
    }

    public function getConnections()
    {
        return $this->connections;
    }

    public function getQueryCount()
    {
        return array_sum(array_map('count', $this->queries));
    }

    public function getQueries()
    {
        return $this->queries;
    }

    public function getTime()
    {
        $time = 0;
        foreach ($this->queries as $queries) {
            foreach ($queries as $query) {
                $time += $query['executionMS'];
            }
        }

        return $time;
    }

    public function getEntities()
    {
        return $this->entities;
    }

    public function getMappingErrors()
    {
        return $this->errors;
    }

    public function getCacheHitsCount()
    {
        return $this->cacheCounts['hits'];
    }

    public function getCachePutsCount()
    {
        return $this->cacheCounts['puts'];
    }

    public function getCacheMissesCount()
    {
        return $this->cacheCounts['misses'];
    }

    public function getCacheEnabled()
    {
        return $this->cacheEnabled;
    }

    public function getCacheRegions()
    {
        return $this->cacheRegions;
    }

    public function getCacheCounts()
    {
        return $this->cacheCounts;
    }

    public function getInvalidEntityCount()
    {
        if (null === $this->invalidEntityCount) {
            $this->invalidEntityCount = array_sum(array_map('count', $this->errors));
        }

        return $this->invalidEntityCount;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        return serialize(array(
            'queries' => $this->queries,
            'connections' => $this->connections,
            'managers' => $this->managers,
            'invalidEntityCount' => $this->invalidEntityCount,
            'cacheEnabled' => $this->cacheEnabled,
            'cacheLogEnabled' => $this->cacheLogEnabled,
            'cacheCounts' => $this->cacheCounts,
            'cacheRegions' => $this->cacheRegions,
            'errors' => $this->errors,
            'entities' => $this->entities,
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
        $unserialized = unserialize($serialized);
        $this->queries = $unserialized['queries'];
        $this->connections = $unserialized['connections'];
        $this->managers = $unserialized['managers'];
        $this->invalidEntityCount = $unserialized['invalidEntityCount'];
        $this->cacheEnabled = $unserialized['cacheEnabled'];
        $this->cacheLogEnabled = $unserialized['cacheLogEnabled'];
        $this->cacheCounts = $unserialized['cacheCounts'];
        $this->cacheRegions = $unserialized['cacheRegions'];
        $this->errors = $unserialized['errors'];
        $this->entities = $unserialized['entities'];
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'db';
    }
}