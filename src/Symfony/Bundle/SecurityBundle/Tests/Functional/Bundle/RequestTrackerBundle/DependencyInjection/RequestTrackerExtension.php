<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\RequestTrackerBundle\DependencyInjection;

use Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\RequestTrackerBundle\EventSubscriber\RequestTrackerSubscriber;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

final class RequestTrackerExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $container->register('request_tracker_subscriber', RequestTrackerSubscriber::class)
            ->setPublic(true)
            ->addTag('kernel.event_subscriber');
    }
}
