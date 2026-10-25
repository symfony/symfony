<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\DoctrineOrm\DependencyInjection;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\EventListener\EncryptedColumnListener;

/**
 * @internal
 */
final class ConfigureEncryptedColumns
{
    public function __construct(
        private readonly EncryptedColumnListener $listener,
        private readonly string $name,
        private readonly ?\Closure $previous = null,
    ) {
    }

    public function __invoke(EntityManagerInterface $manager): void
    {
        if (null !== $this->previous) {
            ($this->previous)($manager);
        }

        $this->listener->register($manager, $this->name);
    }
}
