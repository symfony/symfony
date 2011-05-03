<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineBundle\Command\Proxy;

use Symfony\Component\Console\Application;
use Doctrine\DBAL\Tools\Console\Helper\ConnectionHelper;
use Doctrine\ORM\Tools\Console\Helper\EntityManagerHelper;

/**
 * Provides some helper and convenience methods to configure doctrine commands in the context of bundles
 * and multiple connections/entity managers.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class DoctrineCommandHelper
{
    /**
     * Convenience method to push the helper sets of a given entity manager into the application.
     *
     * @param string      $emName
     */
    static public function setApplicationEntityManager(Application $application, $emName)
    {
        $em = self::getEntityManager($application, $emName);
        $helperSet = $application->getHelperSet();
        $helperSet->set(new ConnectionHelper($em->getConnection()), 'db');
        $helperSet->set(new EntityManagerHelper($em), 'em');
    }

    static public function setApplicationConnection(Application $application, $connName)
    {
        $connection = self::getDoctrineConnection($application, $connName);
        $helperSet = $application->getHelperSet();
        $helperSet->set(new ConnectionHelper($connection), 'db');
    }

    static protected function getEntityManager(Application $application, $name)
    {
        $container = $application->getKernel()->getContainer();

        $name = $name ?: $container->getParameter('doctrine.orm.default_entity_manager');

        $ems = $container->getParameter('doctrine.orm.entity_managers');
        if (!isset($ems[$name])) {
            throw new \InvalidArgumentException(sprintf('Could not find Doctrine EntityManager named "%s"', $name));
        }

        return $container->get($ems[$name]);
    }

    /**
     * Get a doctrine dbal connection by symfony name.
     *
     * @param string $name
     * @return Doctrine\DBAL\Connection
     */
    static protected function getDoctrineConnection(Application $application, $name)
    {
        $container = $application->getKernel()->getContainer();

        $name = $name ?: $container->getParameter('doctrine.dbal.default_connection');

        $connections = $container->getParameter('doctrine.dbal.connections');
        if (!isset($connections[$name])) {
            throw new \InvalidArgumentException(sprintf('<error>Could not find a connection named <comment>%s</comment></error>', $name));
        }

        return $container->get($connections[$name]);
    }
}
