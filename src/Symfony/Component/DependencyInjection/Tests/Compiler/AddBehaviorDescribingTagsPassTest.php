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
use Symfony\Component\DependencyInjection\Compiler\DecoratorServicePass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class AddBehaviorDescribingTagsPassTest extends TestCase
{
    public function testProcessSetsParameter()
    {
        $container = new ContainerBuilder();

        (new AddBehaviorDescribingTagsPass(['kernel.event_subscriber', 'kernel.reset']))->process($container);

        $this->assertSame([
            'proxy',
            'container.do_not_inline',
            'container.service_locator',
            'container.service_subscriber',
            'container.service_subscriber.locator',
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
            'proxy',
            'container.do_not_inline',
            'container.service_locator',
            'container.service_subscriber',
            'container.service_subscriber.locator',
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

    /**
     * @see https://github.com/symfony/symfony/issues/64467
     */
    public function testDefaultTagsCoverServiceSubscriberLocatorAndProxyTagsKeptByDecoratorServicePass()
    {
        $container = new ContainerBuilder();
        $container
            ->register('foo')
            ->setTags([
                'proxy' => 'foo',
                'container.service_subscriber' => [],
                'container.service_subscriber.locator' => [],
                'bar' => ['attr' => 'baz'],
            ])
        ;
        $container
            ->register('baz')
            ->setTags(['foobar' => ['attr' => 'bar']])
            ->setDecoratedService('foo')
        ;

        (new AddBehaviorDescribingTagsPass())->process($container);
        (new DecoratorServicePass())->process($container);

        $this->assertEquals([
            'proxy' => 'foo',
            'container.service_subscriber' => [],
            'container.service_subscriber.locator' => [],
        ], $container->getDefinition('baz.inner')->getTags());
        $this->assertEquals([
            'bar' => ['attr' => 'baz'],
            'foobar' => ['attr' => 'bar'],
            'container.decorator' => [['id' => 'foo', 'inner' => 'baz.inner']],
        ], $container->getDefinition('baz')->getTags());
    }
}
