<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\WebLink\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\WebLink\EventListener\AddLinkHeaderListener;
use Symfony\Component\WebLink\HttpHeaderSerializer;
use Symfony\Component\WebLink\WebLinkBundle;

class WebLinkBundleTest extends TestCase
{
    private string $varDir;

    protected function setUp(): void
    {
        $this->varDir = sys_get_temp_dir().'/sf_web_link_bundle_test';
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->varDir);
    }

    public function testLinkHeaderListenerIsRegistered()
    {
        $kernel = new TestWebLinkKernel('test', true, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();

        $this->assertInstanceOf(AddLinkHeaderListener::class, $container->get('test.web_link.add_link_header_listener'));
        $this->assertInstanceOf(HttpHeaderSerializer::class, $container->get('test.web_link.http_header_serializer'));
    }
}

class TestWebLinkKernel extends AbstractKernel
{
    use KernelTrait;

    public function __construct(string $env, bool $debug, private string $dir)
    {
        parent::__construct($env, $debug);
    }

    public function getProjectDir(): string
    {
        return $this->dir;
    }

    public function registerBundles(): iterable
    {
        yield new WebLinkBundle();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->services()->alias('test.web_link.add_link_header_listener', 'web_link.add_link_header_listener')->public();
        $container->services()->alias('test.web_link.http_header_serializer', 'web_link.http_header_serializer')->public();
    }
}
