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

use Doctrine\ORM\Mapping\ChainTypedFieldMapper;
use Doctrine\ORM\Mapping\DefaultTypedFieldMapper;
use Symfony\Bridge\Doctrine\Types\DatePointType;
use Symfony\Bridge\Doctrine\Types\DayPointType;
use Symfony\Bridge\Doctrine\Types\TimePointType;
use Symfony\Component\Clock\DatePoint;
use Symfony\Component\Clock\DayPoint;
use Symfony\Component\Clock\TimePoint;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

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

        if (!class_exists(DefaultTypedFieldMapper::class)) {
            return;
        }

        $mapperDefinition = new Definition(DefaultTypedFieldMapper::class, [[DatePoint::class => 'date_point', DayPoint::class => 'day_point', TimePoint::class => 'time_point']]);

        foreach ($container->getDefinitions() as $id => $configuration) {
            if (!preg_match('/^doctrine\.orm\.\w+_configuration$/D', $id)) {
                continue;
            }

            $mapper = $mapperDefinition;
            $calls = [];
            foreach ($configuration->getMethodCalls() as $call) {
                if ('setTypedFieldMapper' !== $call[0]) {
                    $calls[] = $call;
                    continue;
                }

                // a mapper configured by the application keeps the first say
                $mapper = new Definition(ChainTypedFieldMapper::class, [$call[1][0], $mapperDefinition]);
            }

            $calls[] = ['setTypedFieldMapper', [$mapper]];
            $configuration->setMethodCalls($calls);
        }
    }
}
