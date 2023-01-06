<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Repository\RepositoryFactory;
use Doctrine\Persistence\ObjectRepository;

/**
 * @author Andreas Braun <alcaeus@alcaeus.org>
 */
final class TestRepositoryFactory implements RepositoryFactory
{
    /**
     * @var array<string, ObjectRepository>
     */
    private array $repositoryList = [];

    public function getRepository(EntityManagerInterface $entityManager, $entityName): ObjectRepository
    {
        $repositoryHash = $this->getRepositoryHash($entityManager, $entityName);

        return $this->repositoryList[$repositoryHash] ??= $this->createRepository($entityManager, $entityName);
    }

    public function setRepository(EntityManagerInterface $entityManager, string $entityName, ObjectRepository $repository): void
    {
        $repositoryHash = $this->getRepositoryHash($entityManager, $entityName);

        $this->repositoryList[$repositoryHash] = $repository;
    }

    private function createRepository(EntityManagerInterface $entityManager, string $entityName): ObjectRepository
    {
        $metadata = $entityManager->getClassMetadata($entityName);
        $repositoryClassName = $metadata->customRepositoryClassName ?: $entityManager->getConfiguration()->getDefaultRepositoryClassName();

        return new $repositoryClassName($entityManager, $metadata);
    }

    private function getRepositoryHash(EntityManagerInterface $entityManager, string $entityName): string
    {
        return $entityManager->getClassMetadata($entityName)->getName().spl_object_hash($entityManager);
    }
}
