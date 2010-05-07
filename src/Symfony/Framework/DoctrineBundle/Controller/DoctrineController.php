<?php

namespace Symfony\Framework\DoctrineBundle\Controller;

use Symfony\Framework\WebBundle\Controller;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Doctrine ORM controller gives you access to entity managers and DQL queries.
 *
 * @package    Symfony
 * @subpackage Framework_DoctrineBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author     Jonathan H. Wage <jonwage@gmail.com>
 */
class DoctrineController extends Controller
{
    public function getDatabaseConnection($name = null)
    {
        if ($name) {
            return $this->container->getService(sprintf('doctrine.dbal.%s_connection', $name));
        } else {
            return $this->container->getDatabaseConnectionService();
        }
    }

    /**
     * Get the default entity manager service or the entity manager
     * with the given name.
     *
     * @param string $name Optional entity manager service name
     *
     * @return object
     */
    protected function getEntityManager($name = null)
    {
        if ($name) {
            return $this->container->getService(sprintf('doctrine.orm.%s_entity_manager', $name));
        } else {
            return $this->container->getDoctrine_ORM_EntityManagerService();
        }
    }

    /**
     * Create a new QueryBuilder instance.
     *
     * @param string $name Optional entity manager service name
     * @return object QueryBuilder
     */
    public function createQueryBuilder($name = null)
    {
        return $this->getEntityManager($name)->createQueryBuilder();
    }

    /**
     * Create a new Query instance.
     *
     * @param string $dql  Optional Dql string to create the query from
     * @param string $name Optional entity manager service name
     *
     * @return object QueryBuilder
     */
    public function createQuery($dql = '', $name = null)
    {
        return $this->getEntityManager($name)->createQuery($dql);
    }
}
