<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass;

use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Uid\AbstractUid;

final class RegisterUidTypePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!class_exists(AbstractUid::class)) {
            return;
        }

        if (!$container->hasParameter('doctrine.dbal.connection_factory.types')) {
            return;
        }

        $typeDefinition = $container->getParameter('doctrine.dbal.connection_factory.types');

        if (!isset($typeDefinition['uuid'])) {
            $typeDefinition['uuid'] = ['class' => UuidType::class];
        }

        if (!isset($typeDefinition['ulid'])) {
            $typeDefinition['ulid'] = ['class' => UlidType::class];
        }

        $container->setParameter('doctrine.dbal.connection_factory.types', $typeDefinition);
    }
}
