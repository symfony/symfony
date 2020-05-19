<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Scheduler\TraceableScheduler;
use Symfony\Component\Scheduler\Transport\TraceableTransport;
use Symfony\Component\Scheduler\Worker\TraceableWorker;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerPass implements CompilerPassInterface
{
    private $schedulerTag;
    private $transportTag;
    private $workerTag;
    private $schedulerEntryPointTag;

    public function __construct(string $schedulerTag = 'scheduler.hub', string $transportTag = 'scheduler.transport', string $workerTag = 'scheduler.worker', string $schedulerEntryPointTag = 'scheduler.entry_point')
    {
        $this->schedulerTag = $schedulerTag;
        $this->transportTag = $transportTag;
        $this->workerTag = $workerTag;
        $this->schedulerEntryPointTag = $schedulerEntryPointTag;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $this->registerSchedulerToCollector($container);
        $this->registerWorkerToCollector($container);
        $this->registerTraceableTransport($container);
        $this->registerSchedulerEntrypoint($container);
        $this->triggerCronGeneration($container);
    }

    private function registerSchedulerToCollector(ContainerBuilder $container): void
    {
        foreach ($container->findTaggedServiceIds($this->schedulerTag) as $schedulerId => $tags) {
            $container->setDefinition(
                $tracedId = 'debug.scheduler.hub.'.$schedulerId,
                (new Definition(TraceableScheduler::class, [new Reference($tracedId.'.inner')]))->setDecoratedService($schedulerId)
            );
            $container->getDefinition('scheduler.data_collector')->addMethodCall('registerScheduler', [$schedulerId, new Reference($tracedId)]);
        }
    }

    private function registerWorkerToCollector(ContainerBuilder $container): void
    {
        foreach ($container->findTaggedServiceIds($this->workerTag) as $workerId => $tags) {
            $container->setDefinition(
                $tracedId = 'debug.scheduler.worker.'.$workerId,
                (new Definition(TraceableWorker::class, [new Reference($tracedId.'.inner')]))->setDecoratedService($workerId)
            );
            $container->getDefinition('scheduler.data_collector')->addMethodCall('registerWorker', [$workerId, new Reference($tracedId)]);
        }
    }

    public function registerTraceableTransport(ContainerBuilder $container): void
    {
        foreach ($container->findTaggedServiceIds($this->transportTag) as $transportId => $tags) {
            $container->setDefinition(
                $tracedId = 'debug.scheduler.transport.'.$transportId,
                (new Definition(TraceableTransport::class, [
                    new Reference($tracedId.'.inner'),
                    new Reference('logger')
                ]))->setDecoratedService($transportId)
            );
            $container->getDefinition('scheduler.data_collector')->addMethodCall('registerTransport', [$transportId, new Reference($tracedId)]);
        }
    }

    private function registerSchedulerEntryPoint(ContainerBuilder $container): void
    {
        foreach ($container->findTaggedServiceIds('scheduler.entry_point') as $entryPointsId => $tags) {
            $container->getDefinition($entryPointsId)->addMethodCall('schedule', [new Reference('scheduler.registry')]);
        }
    }

    private function triggerCronGeneration(ContainerBuilder $container): void
    {
        $schedulers = [];

        foreach ($container->findTaggedServiceIds($this->schedulerTag) as $schedulerId => $tags) {
            $schedulers[$schedulerId] = $tags[0];
        }

        foreach ($schedulers as $scheduler) {
            if (!\array_key_exists('alias', $scheduler) && !\array_key_exists('transport', $scheduler)) {
                continue;
            }

            $container->getDefinition('scheduler.cron.factory')->addMethodCall('create', [
                $scheduler['alias'],
                new Reference($scheduler['transport']),
                ['path' => $container->getParameter('kernel.project_dir')]
            ]);
        }
    }
}
