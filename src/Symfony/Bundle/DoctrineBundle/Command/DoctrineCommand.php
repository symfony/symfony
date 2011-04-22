<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Doctrine\DBAL\Tools\Console\Helper\ConnectionHelper;
use Doctrine\ORM\Tools\Console\Helper\EntityManagerHelper;
use Doctrine\ORM\Tools\DisconnectedClassMetadataFactory;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Tools\EntityGenerator;

/**
 * Base class for Doctrine console commands to extend from.
 *
 * Provides some helper and convenience methods to configure doctrine commands in the context of bundles
 * and multiple connections/entity managers.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class DoctrineCommand extends Command
{
    /**
     * Convenience method to push the helper sets of a given entity manager into the application.
     *
     * @param Application $application
     * @param string $emName
     */
    public static function setApplicationEntityManager(Application $application, $emName)
    {
        $container = $application->getKernel()->getContainer();
        $em = self::getEntityManager($container, $emName);
        $helperSet = $application->getHelperSet();
        $helperSet->set(new ConnectionHelper($em->getConnection()), 'db');
        $helperSet->set(new EntityManagerHelper($em), 'em');
    }

    public static function setApplicationConnection(Application $application, $connName)
    {
        $container = $application->getKernel()->getContainer();
        $connName = $connName ? $connName : $container->getParameter('doctrine.dbal.default_connection');
        $connServiceName = sprintf('doctrine.dbal.%s_connection', $connName);
        if (!$container->has($connServiceName)) {
            throw new \InvalidArgumentException(sprintf('Could not find Doctrine Connection named "%s"', $connName));
        }

        $connection = $container->get($connServiceName);
        $helperSet = $application->getHelperSet();
        $helperSet->set(new ConnectionHelper($connection), 'db');
    }

    protected function getEntityGenerator()
    {
        $entityGenerator = new EntityGenerator();

        if (version_compare(\Doctrine\ORM\Version::VERSION, "2.0.2-DEV") >= 0) {
            $entityGenerator->setAnnotationPrefix("orm:");
        }
        $entityGenerator->setGenerateAnnotations(false);
        $entityGenerator->setGenerateStubMethods(true);
        $entityGenerator->setRegenerateEntityIfExists(false);
        $entityGenerator->setUpdateEntityIfExists(true);
        $entityGenerator->setNumSpaces(4);
        return $entityGenerator;
    }

    protected static function getEntityManager($container, $name = null)
    {
        $name = $name ? $name : $container->getParameter('doctrine.orm.default_entity_manager');
        $serviceName = sprintf('doctrine.orm.%s_entity_manager', $name);
        if (!$container->has($serviceName)) {
            throw new \InvalidArgumentException(sprintf('Could not find Doctrine EntityManager named "%s"', $name));
        }

        return $container->get($serviceName);
    }

    /**
     * Get a doctrine dbal connection by symfony name.
     *
     * @param string $name
     * @return Doctrine\DBAL\Connection
     */
    protected function getDoctrineConnection($name)
    {
        $connectionName = $name ?: $this->container->getParameter('doctrine.dbal.default_connection');
        $connectionName = sprintf('doctrine.dbal.%s_connection', $connectionName);
        if (!$this->container->has($connectionName)) {
            throw new \InvalidArgumentException(sprintf('<error>Could not find a connection named <comment>%s</comment></error>', $name));
        }
        return $this->container->get($connectionName);
    }

    protected function getDoctrineEntityManagers()
    {
        $entityManagerNames = $this->container->getParameter('doctrine.orm.entity_managers');
        $entityManagers = array();
        foreach ($entityManagerNames as $entityManagerName) {
            $em = $this->container->get(sprintf('doctrine.orm.%s_entity_manager', $entityManagerName));
            $entityManagers[] = $em;
        }
        return $entityManagers;
    }

    protected function getBundleMetadatas(Bundle $bundle)
    {
        $namespace = $bundle->getNamespace();
        $bundleMetadatas = array();
        $entityManagers = $this->getDoctrineEntityManagers();
        foreach ($entityManagers as $key => $em) {
            $cmf = new DisconnectedClassMetadataFactory();
            $cmf->setEntityManager($em);
            $metadatas = $cmf->getAllMetadata();
            foreach ($metadatas as $metadata) {
                if (strpos($metadata->name, $namespace) === 0) {
                    $bundleMetadatas[$metadata->name] = $metadata;
                }
            }
        }

        return $bundleMetadatas;
    }

    protected function findBundle($bundleName)
    {
        $foundBundle = false;
        foreach ($this->getApplication()->getKernel()->getBundles() as $bundle) {
            /* @var $bundle Bundle */
            if (strtolower($bundleName) == strtolower($bundle->getName())) {
                $foundBundle = $bundle;
                break;
            }
        }

        if (!$foundBundle) {
            throw new \InvalidArgumentException("No bundle " . $bundleName . " was found.");
        }

        return $foundBundle;
    }

    /**
     * Transform classname to a path $foundBundle substract it to get the destination
     *
     * @param Bundle $bundle
     * @return string
     */
    protected function findBasePathForBundle($bundle)
    {
        $path = str_replace('\\', '/', $bundle->getNamespace());
        $search = str_replace('\\', '/', $bundle->getPath());
        $destination = str_replace('/'.$path, '', $search, $c);

        if ($c != 1) {
            throw new \RuntimeException(sprintf('Can\'t find base path for bundle (path: "%s", destination: "%s").', $path, $destination));
        }

        return $destination;
    }
}
