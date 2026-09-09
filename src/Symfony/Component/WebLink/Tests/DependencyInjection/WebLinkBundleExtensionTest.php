<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\WebLink\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\WebLink\WebLinkBundle;

class WebLinkBundleExtensionTest extends TestCase
{
    public function testWebLink()
    {
        $container = $this->createContainerFromFile('web_link');

        $this->assertTrue($container->hasDefinition('web_link.add_link_header_listener'));
        $this->assertTrue($container->hasDefinition('web_link.http_header_serializer'));
        $this->assertTrue($container->hasDefinition('web_link.http_header_parser'));
        $this->assertTrue($container->hasDefinition('web_link.link_template_header_serializer'));
        $this->assertTrue($container->hasDefinition('web_link.link_template_header_parser'));
        $this->assertTrue($container->hasDefinition('web_link.json_linkset_serializer'));
        $this->assertTrue($container->hasDefinition('web_link.json_linkset_parser'));

        $listener = $container->getDefinition('web_link.add_link_header_listener');
        $this->assertSame('web_link.http_header_serializer', (string) $listener->getArgument(0));
        $this->assertSame('web_link.link_template_header_serializer', (string) $listener->getArgument(1));
        $this->assertArrayHasKey('kernel.event_subscriber', $listener->getTags());
    }

    public function testWebLinkDisabled()
    {
        $container = $this->createContainerFromFile('web_link_disabled');

        $this->assertFalse($container->hasDefinition('web_link.add_link_header_listener'));
        $this->assertFalse($container->hasDefinition('web_link.http_header_serializer'));
    }

    private function createContainerFromFile(string $file): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->registerExtension(new WebLinkBundle()->getContainerExtension());
        $container->getCompilerPassConfig()->setBeforeOptimizationPasses([]);
        $container->getCompilerPassConfig()->setOptimizationPasses([]);
        $container->getCompilerPassConfig()->setBeforeRemovingPasses([]);
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->getCompilerPassConfig()->setAfterRemovingPasses([]);

        (new PhpFileLoader($container, new FileLocator(__DIR__.'/Fixtures')))->load($file.'.php');
        $container->compile();

        return $container;
    }
}
