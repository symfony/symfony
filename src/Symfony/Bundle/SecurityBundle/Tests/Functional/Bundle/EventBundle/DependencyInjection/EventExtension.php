<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\EventBundle\DependencyInjection;

use Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\EventBundle\EventSubscriber\TestSubscriber;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

final class EventExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $container->register('test_subscriber', TestSubscriber::class)
            ->setPublic(true)
            ->addTag('kernel.event_subscriber');
    }
}
