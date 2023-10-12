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

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Repository\RepositoryFactory;
use Doctrine\Persistence\ObjectRepository;

if ((new \ReflectionMethod(RepositoryFactory::class, 'getRepository'))->hasReturnType()) {
    /** @internal */
    trait GetRepositoryTrait
    {
        public function getRepository(EntityManagerInterface $entityManager, string $entityName): EntityRepository
        {
            return $this->doGetRepository($entityManager, $entityName);
        }
    }
} else {
    /** @internal */
    trait GetRepositoryTrait
    {
        /**
         * {@inheritdoc}
         *
         * @return ObjectRepository
         */
        public function getRepository(EntityManagerInterface $entityManager, $entityName)
        {
            return $this->doGetRepository($entityManager, $entityName);
        }
    }
}

/**
 * @author Andreas Braun <alcaeus@alcaeus.org>
 *
 * @deprecated since Symfony 5.3
 */
class TestRepositoryFactory implements RepositoryFactory
{
    use GetRepositoryTrait;

    /**
     * @var ObjectRepository[]
     */
    private $repositoryList = [];

    private function doGetRepository(EntityManagerInterface $entityManager, string $entityName): ObjectRepository
    {
        if (__CLASS__ === static::class) {
            trigger_deprecation('symfony/doctrine-bridge', '5.3', '"%s" is deprecated and will be removed in 6.0.', __CLASS__);
        }

        $repositoryHash = $this->getRepositoryHash($entityManager, $entityName);

        if (isset($this->repositoryList[$repositoryHash])) {
            return $this->repositoryList[$repositoryHash];
        }

        return $this->repositoryList[$repositoryHash] = $this->createRepository($entityManager, $entityName);
    }

    public function setRepository(EntityManagerInterface $entityManager, string $entityName, ObjectRepository $repository)
    {
        if (__CLASS__ === static::class) {
            trigger_deprecation('symfony/doctrine-bridge', '5.3', '"%s" is deprecated and will be removed in 6.0.', __CLASS__);
        }

        $repositoryHash = $this->getRepositoryHash($entityManager, $entityName);

        $this->repositoryList[$repositoryHash] = $repository;
    }

    private function createRepository(EntityManagerInterface $entityManager, string $entityName): ObjectRepository
    {
        /* @var $metadata ClassMetadata */
        $metadata = $entityManager->getClassMetadata($entityName);
        $repositoryClassName = $metadata->customRepositoryClassName ?: $entityManager->getConfiguration()->getDefaultRepositoryClassName();

        return new $repositoryClassName($entityManager, $metadata);
    }

    private function getRepositoryHash(EntityManagerInterface $entityManager, string $entityName): string
    {
        return $entityManager->getClassMetadata($entityName)->getName().spl_object_hash($entityManager);
    }
}
