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

use Symfony\Bridge\Doctrine\Types\DatePointType;
use Symfony\Bridge\Doctrine\Types\DayPointType;
use Symfony\Bridge\Doctrine\Types\TimePointType;
use Symfony\Component\Clock\DatePoint;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class RegisterDatePointTypePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!class_exists(DatePoint::class)) {
            return;
        }

        if (!$container->hasParameter('doctrine.dbal.connection_factory.types')) {
            return;
        }

        $types = $container->getParameter('doctrine.dbal.connection_factory.types');

        $types['date_point'] ??= ['class' => DatePointType::class];
        $types['day_point'] ??= ['class' => DayPointType::class];
        $types['time_point'] ??= ['class' => TimePointType::class];

        $container->setParameter('doctrine.dbal.connection_factory.types', $types);
    }
}
