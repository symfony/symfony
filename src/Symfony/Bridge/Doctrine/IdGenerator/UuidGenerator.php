<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\IdGenerator;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Id\AbstractIdGenerator;
use Symfony\Component\Uid\Factory\UuidFactory;
use Symfony\Component\Uid\Uuid;

final class UuidGenerator extends AbstractIdGenerator
{
    private $protoFactory;
    private $factory;
    private ?string $entityGetter = null;

    public function __construct(UuidFactory $factory = null)
    {
        $this->protoFactory = $this->factory = $factory ?? new UuidFactory();
    }

    /**
     * doctrine/orm < 2.11 BC layer.
     */
    public function generate(EntityManager $em, $entity): Uuid
    {
        return $this->generateId($em, $entity);
    }

    public function generateId(EntityManagerInterface $em, $entity): Uuid
    {
        if (null !== $this->entityGetter) {
            if (\is_callable([$entity, $this->entityGetter])) {
                return $this->factory->create($entity->{$this->entityGetter}());
            }

            return $this->factory->create($entity->{$this->entityGetter});
        }

        return $this->factory->create();
    }

    public function nameBased(string $entityGetter, Uuid|string $namespace = null): static
    {
        $clone = clone $this;
        $clone->factory = $clone->protoFactory->nameBased($namespace);
        $clone->entityGetter = $entityGetter;

        return $clone;
    }

    public function randomBased(): static
    {
        $clone = clone $this;
        $clone->factory = $clone->protoFactory->randomBased();
        $clone->entityGetter = null;

        return $clone;
    }

    public function timeBased(Uuid|string $node = null): static
    {
        $clone = clone $this;
        $clone->factory = $clone->protoFactory->timeBased($node);
        $clone->entityGetter = null;

        return $clone;
    }
}
