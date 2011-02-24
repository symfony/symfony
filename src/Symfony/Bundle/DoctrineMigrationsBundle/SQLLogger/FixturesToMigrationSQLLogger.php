<?php

namespace Symfony\Bundle\DoctrineMigrationsBundle\SQLLogger;

class FixturesToMigrationSQLLogger implements \Doctrine\DBAL\Logging\SQLLogger
{
    private $queries = array();

    public function startQuery($sql, array $params = null, array $types = null)
    {
        $this->queries[] = array($sql, $params, $types);
    }

    public function getQueries()
    {
        return $this->queries;
    }

    public function stopQuery()
    {
    }
}