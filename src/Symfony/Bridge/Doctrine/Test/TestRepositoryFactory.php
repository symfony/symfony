<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Test;

use Doctrine\Common\Persistence\ObjectRepository as LegacyObjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Repository\RepositoryFactory;
use Doctrine\Persistence\ObjectRepository;

/**
 * @author Andreas Braun <alcaeus@alcaeus.org>
 */
final class TestRepositoryFactory implements RepositoryFactory
{
    /**
     * @var ObjectRepository[]
     */
    private $repositoryList = [];

    /**
     * {@inheritdoc}
     *
     * @return ObjectRepository|LegacyObjectRepository
     */
    public function getRepository(EntityManagerInterface $entityManager, $entityName)
    {
        $repositoryHash = $this->getRepositoryHash($entityManager, $entityName);

        if (isset($this->repositoryList[$repositoryHash])) {
            return $this->repositoryList[$repositoryHash];
        }

        return $this->repositoryList[$repositoryHash] = $this->createRepository($entityManager, $entityName);
    }

    public function setRepository(EntityManagerInterface $entityManager, $entityName, LegacyObjectRepository $repository)
    {
        $repositoryHash = $this->getRepositoryHash($entityManager, $entityName);

        $this->repositoryList[$repositoryHash] = $repository;
    }

    /**
     * @return ObjectRepository|LegacyObjectRepository
     */
    private function createRepository(EntityManagerInterface $entityManager, $entityName)
    {
        /* @var $metadata ClassMetadata */
        $metadata = $entityManager->getClassMetadata($entityName);
        $repositoryClassName = $metadata->customRepositoryClassName ?: $entityManager->getConfiguration()->getDefaultRepositoryClassName();

        return new $repositoryClassName($entityManager, $metadata);
    }

    private function getRepositoryHash(EntityManagerInterface $entityManager, $entityName)
    {
        return $entityManager->getClassMetadata($entityName)->getName().spl_object_hash($entityManager);
    }
}
