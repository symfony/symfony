<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TypeInfo\Tests;

use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyWithConfiguredTypeAlias;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeInfoBundle;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolverInterface;

class TypeInfoBundleTest extends TestCase
{
    private string $varDir;

    protected function setUp(): void
    {
        $this->varDir = sys_get_temp_dir().'/sf_type_info_bundle_test';
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->varDir);
    }

    public function testResolverIsRegistered()
    {
        $kernel = new TestTypeInfoKernel('test', true, $this->varDir);
        $kernel->boot();

        $resolver = $kernel->getContainer()->get('test.type_info.resolver');
        $this->assertInstanceOf(TypeResolverInterface::class, $resolver);
        $this->assertEquals(Type::string(), $resolver->resolve(new \ReflectionProperty(DummyWithConfiguredTypeAlias::class, 'name')));
    }

    public function testTypeAliasesAreResolved()
    {
        if (!class_exists(PhpDocParser::class)) {
            $this->markTestSkipped('"phpstan/phpdoc-parser" dependency is required.');
        }

        $kernel = new TestTypeInfoKernel('test', true, $this->varDir);
        $kernel->boot();

        $resolver = $kernel->getContainer()->get('test.type_info.resolver');
        $this->assertEquals(Type::int(), $resolver->resolve('CustomAlias'));
        $this->assertEquals(Type::int(), $resolver->resolve(new \ReflectionProperty(DummyWithConfiguredTypeAlias::class, 'customAlias')));
    }
}

class TestTypeInfoKernel extends AbstractKernel
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
        yield new TypeInfoBundle();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('type_info', [
            'aliases' => [
                'CustomAlias' => 'int',
            ],
        ]);
        $container->services()->alias('test.type_info.resolver', 'type_info.resolver')->public();
    }
}
