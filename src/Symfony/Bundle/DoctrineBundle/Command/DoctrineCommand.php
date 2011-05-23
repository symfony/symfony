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
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Doctrine\ORM\Tools\DisconnectedClassMetadataFactory;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Tools\EntityGenerator;
use Doctrine\ORM\Version as DoctrineVersion;
use Doctrine\ORM\ORMException;

/**
 * Base class for Doctrine console commands to extend from.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class DoctrineCommand extends Command
{
    protected function getEntityGenerator()
    {
        $entityGenerator = new EntityGenerator();
        $entityGenerator->setGenerateAnnotations(false);
        $entityGenerator->setGenerateStubMethods(true);
        $entityGenerator->setRegenerateEntityIfExists(false);
        $entityGenerator->setUpdateEntityIfExists(true);
        $entityGenerator->setNumSpaces(4);

        return $entityGenerator;
    }

    protected function getEntityManager($name)
    {
        return $this->container->get('doctrine')->getEntityManager($name);
    }

    /**
     * Get a doctrine dbal connection by symfony name.
     *
     * @param string $name
     * @return Doctrine\DBAL\Connection
     */
    protected function getDoctrineConnection($name)
    {
        return $this->container->get('doctrine')->getConnection($name);
    }

    protected function findMetadatasByNamespace($namespace)
    {
        $metadatas = array();
        foreach ($this->findAllMetadatas() as $name => $metadata) {
            if (strpos($name, $namespace) === 0) {
                $metadatas[$name] = $metadata;
            }
        }

        return $metadatas;
    }

    protected function findMetadatasByClass($entity)
    {
        foreach ($this->findAllMetadatas() as $name => $metadata) {
            if ($name === $entity) {
                return array($name => $metadata);
            }
        }

        return array();
    }

    protected function findAllMetadatas()
    {
        $metadatas = array();
        foreach ($this->container->get('doctrine')->getEntityManagerNames() as $id) {
            $cmf = new DisconnectedClassMetadataFactory();
            $cmf->setEntityManager($this->container->get($id));
            foreach ($cmf->getAllMetadata() as $metadata) {
                $metadatas[$metadata->name] = $metadata;
            }
        }

        return $metadatas;
    }

    /**
     * Transform classname to a path $foundBundle substract it to get the destination
     *
     * @param Bundle $bundle
     * @return string
     */
    protected function findBasePathForClass($name, $namespace, $path)
    {
        $namespace = str_replace('\\', '/', $namespace);
        $search = str_replace('\\', '/', $path);
        $destination = str_replace('/'.$namespace, '', $search, $c);

        if ($c != 1) {
            throw new \RuntimeException(sprintf('Can\'t find base path for "%s" (path: "%s", destination: "%s").', $name, $path, $destination));
        }

        return $destination;
    }

    protected function getAliasedClassName($name)
    {
        $pos = strpos($name, ':');
        $alias = substr($name, 0, $pos);

        foreach ($this->container->get('doctrine')->getEntityManagerNames() as $id) {
            $em = $this->container->get($id);

            try {
                return $em->getConfiguration()->getEntityNamespace($alias).'\\'.substr($name, $pos + 1);
            } catch (ORMException $e) {
            }
        }

        throw new \RuntimeException(sprintf('Entity "%s" does not exist.', $name));
    }
}
