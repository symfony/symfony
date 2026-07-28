<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Kernel;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\Filesystem\Filesystem;

class ContainerInterfaceStubTest extends TestCase
{
    private string $varDir;

    protected function setUp(): void
    {
        $this->varDir = sys_get_temp_dir().'/sf_container_interface_stub_test';
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->varDir);
    }

    public function testBootDumpsContainerInterfaceStub()
    {
        $kernel = new ContainerStubKernel('test', true, $this->varDir);
        $kernel->boot();

        $stub = $kernel->getContainer()->getParameter('kernel.build_dir').'/stub/ContainerInterface.php';

        $this->assertFileExists($stub);
        $contents = file_get_contents($stub);
        $this->assertStringContainsString("'bar': \\stdClass,", $contents);
        $this->assertStringContainsString("'foo': \\stdClass,", $contents);
        $this->assertStringNotContainsString('private_foo', $contents);
    }
}

class ContainerStubKernel extends AbstractKernel
{
    use KernelTrait {
        registerBundles as public;
    }

    public function __construct(string $env, bool $debug, private string $dir)
    {
        parent::__construct($env, $debug);
    }

    public function getProjectDir(): string
    {
        return $this->dir;
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->register('foo', \stdClass::class)->setPublic(true);
        $container->register('private_foo', \stdClass::class);
        $container->setAlias('bar', 'foo')->setPublic(true);
    }
}
