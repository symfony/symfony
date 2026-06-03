<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Compiler\AddBehaviorDescribingTagsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class AddBehaviorDescribingTagsPassTest extends TestCase
{
    public function testProcessSetsParameter()
    {
        $container = new ContainerBuilder();

        (new AddBehaviorDescribingTagsPass(['kernel.event_subscriber', 'kernel.reset']))->process($container);

        $this->assertSame([
            'container.do_not_inline',
            'container.service_locator',
            'container.service_subscriber',
            'kernel.event_subscriber',
            'kernel.reset',
        ], $container->getParameter('container.behavior_describing_tags'));
    }

    public function testMultiplePassesMerge()
    {
        $container = new ContainerBuilder();

        (new AddBehaviorDescribingTagsPass(['kernel.event_subscriber', 'kernel.reset']))->process($container);
        (new AddBehaviorDescribingTagsPass(['kernel.locale_aware']))->process($container);

        $this->assertSame([
            'container.do_not_inline',
            'container.service_locator',
            'container.service_subscriber',
            'kernel.event_subscriber',
            'kernel.reset',
            'kernel.locale_aware',
        ], $container->getParameter('container.behavior_describing_tags'));
    }

    public function testProcessWithExistingParameter()
    {
        $container = new ContainerBuilder();
        $container->setParameter('container.behavior_describing_tags', ['existing.tag']);

        (new AddBehaviorDescribingTagsPass(['new.tag']))->process($container);

        $this->assertSame(['existing.tag', 'new.tag'], $container->getParameter('container.behavior_describing_tags'));
    }
}
