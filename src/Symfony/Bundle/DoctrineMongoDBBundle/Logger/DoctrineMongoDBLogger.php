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
            switch (key($query)) {
                case 'batchInsert':
                    $this->logger->info(Yaml::dump(array('data' => '[omitted]') + $query, 0));
                    break;
                default:
                    $this->logger->info(Yaml::dump($query, 0));
            }
        }
    }

    public function getNbQueries()
    {
        return $this->nbQueries;
    }
}
