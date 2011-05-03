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

    protected function getBundleMetadatas(Bundle $bundle)
    {
        $namespace = $bundle->getNamespace();
        $bundleMetadatas = array();
        foreach ($this->container->get('doctrine')->getEntityManagerNames() as $id) {
            $em = $this->container->get($id);
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
