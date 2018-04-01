<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symphony\Bundle\FrameworkBundle\DependencyInjection\Compiler\UnusedTagsPass;
use Symphony\Component\DependencyInjection\ContainerBuilder;

class UnusedTagsPassTest extends TestCase
{
    public function testProcess()
    {
        $pass = new UnusedTagsPass();

        $container = new ContainerBuilder();
        $container->register('foo')
            ->addTag('kenrel.event_subscriber');
        $container->register('bar')
            ->addTag('kenrel.event_subscriber');

        $pass->process($container);

        $this->assertSame(array(sprintf('%s: Tag "kenrel.event_subscriber" was defined on service(s) "foo", "bar", but was never used. Did you mean "kernel.event_subscriber"?', UnusedTagsPass::class)), $container->getCompiler()->getLog());
    }
}
