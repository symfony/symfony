<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Scheduler\Bridge\Dkron\Transport\DkronTransportFactory;
use Symfony\Component\Scheduler\Bridge\Doctrine\Transport\DoctrineTransportFactory;
use Symfony\Component\Scheduler\Bridge\Google\Task\JobFactory;
use Symfony\Component\Scheduler\Bridge\Google\Transport\GoogleTransportFactory;
use Symfony\Component\Scheduler\Bridge\Kubernetes\Transport\KubernetesTransportFactory;
use Symfony\Component\Scheduler\Bridge\Redis\Transport\RedisTransportFactory;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $container->services()
        // Dkron
        ->set('scheduler.transport_factory.doctrine', DkronTransportFactory::class)
        ->tag('scheduler.transport_factory')

        // Doctrine
        ->set('scheduler.transport_factory.doctrine', DoctrineTransportFactory::class)
        ->args([
            service('doctrine'),
            service('scheduler.task_factory'),
        ])
        ->tag('scheduler.transport_factory')

        // Google
        ->set('scheduler.job_task.factory', JobFactory::class)
        ->tag('scheduler.task_factory')

        ->set('scheduler.transport_factory.google', GoogleTransportFactory::class)
        ->args([
            service('scheduler.job_task.factory'),
        ])

        // Kubernetes
        ->set('scheduler.transport_factory.doctrine', KubernetesTransportFactory::class)
        ->tag('scheduler.transport_factory')

        // Redis
        ->set('scheduler.transport_factory.redis', RedisTransportFactory::class)
        ->tag('scheduler.transport_factory')
    ;
};
