<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\ContainerBuilderDebugDumpPass;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\DependencyInjection\Argument\BoundArgument;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Filesystem\Filesystem;

class ContainerBuilderDebugDumpPassTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/sf_test_container_builder_debug_dump';
        (new Filesystem())->mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDir);
    }

    public function testSerializedDumpResolvesEnvPlaceholdersWithoutChangingTheContainer()
    {
        $container = $this->createContainer();
        $container->setParameter('foo', 'bar');
        $foo = $container->getParameterBag()->get('env(FOO)');
        $bar = $container->getParameterBag()->get('env(json:BAR)');

        $shared = new Definition('SharedClass', [$foo]);
        $unchanged = new Definition('UnchangedClass', ['plain']);
        $locator = new ServiceLocatorArgument(['bar' => new Reference('bar'), 'foo' => $foo]);

        $container->register('a', 'AClass')
            ->setArguments([$shared, [$bar => ['key' => "prefix-$foo"]], $locator, $unchanged])
            ->setPublic(true);
        $container->register('b', 'BClass')
            ->setArguments([$shared, $unchanged, 'plain'])
            ->setPublic(true);
        $container->register('c', 'CClass')
            ->setArguments([new Reference('b')])
            ->setPublic(true);
        $container->register('call', 'CallClass')->addMethodCall('setFoo', [$foo]);
        $container->register('property', 'PropertyClass')->setProperty('prop', new IteratorArgument([$bar]));
        $container->register('factory', 'FactoryClass')->setFactory([new Definition('FactoryFactory', [$bar]), 'create']);
        $container->register('configurator', 'ConfiguratorClass')->setConfigurator([new Definition('Configurator', [$foo]), 'configure']);
        $container->register('class', "Class\\$foo")->setFile("/path/to/$bar.php");
        $container->setAlias('alias_a', 'a')->setPublic(true);

        $dump = $this->dump($container);

        $this->assertSame(['service_container', 'a', 'b', 'c', 'call', 'property', 'factory', 'configurator', 'class'], array_keys($dump->getDefinitions()));
        $this->assertSame(['alias_a'], array_keys($dump->getAliases()));
        $this->assertSame('bar', $dump->getParameter('foo'));

        $a = $dump->getDefinition('a');
        $b = $dump->getDefinition('b');
        $this->assertSame(['%env(FOO)%'], $a->getArgument(0)->getArguments());
        $this->assertSame($a->getArgument(0), $b->getArgument(0));
        $this->assertSame($a->getArgument(3), $b->getArgument(1));
        $this->assertSame(['plain'], $b->getArgument(1)->getArguments());
        $this->assertSame(['%env(json:BAR)%' => ['key' => 'prefix-%env(FOO)%']], $a->getArgument(1));
        $this->assertSame(['bar', 'foo'], array_keys($a->getArgument(2)->getValues()));
        $this->assertSame('bar', (string) $a->getArgument(2)->getValues()['bar']);
        $this->assertSame('%env(FOO)%', $a->getArgument(2)->getValues()['foo']);
        $this->assertSame('b', (string) $dump->getDefinition('c')->getArgument(0));
        $this->assertSame([['setFoo', ['%env(FOO)%']]], $dump->getDefinition('call')->getMethodCalls());
        $this->assertSame(['%env(json:BAR)%'], $dump->getDefinition('property')->getProperties()['prop']->getValues());
        $this->assertSame(['%env(json:BAR)%'], $dump->getDefinition('factory')->getFactory()[0]->getArguments());
        $this->assertSame(['%env(FOO)%'], $dump->getDefinition('configurator')->getConfigurator()[0]->getArguments());
        $this->assertSame('Class\%env(FOO)%', $dump->getDefinition('class')->getClass());
        $this->assertSame('/path/to/%env(json:BAR)%.php', $dump->getDefinition('class')->getFile());
    }

    public function testSerializedDumpResolvesEnvPlaceholdersSharedWithBindings()
    {
        $container = $this->createContainer();
        $foo = $container->getParameterBag()->get('env(FOO)');
        $bound = new Definition('BoundClass', [$foo]);
        $container->register('a', 'AClass')
            ->setArguments([$bound])
            ->setBindings(['$bound' => new BoundArgument($bound, false), '$foo' => new BoundArgument($foo, false)]);

        $a = $this->dump($container)->getDefinition('a');

        $this->assertSame(['%env(FOO)%'], $a->getArgument(0)->getArguments());
        $this->assertSame($a->getArgument(0), $a->getBindings()['$bound']->getValues()[0]);
        $this->assertSame($foo, $a->getBindings()['$foo']->getValues()[0]);
    }

    public function testSerializedDumpResolvesEnvPlaceholdersSharedWithExcludedDefinitions()
    {
        $container = $this->createContainer();
        $shared = new Definition('SharedClass', [$container->getParameterBag()->get('env(FOO)')]);
        $container->register('a', 'AClass')->setArguments([$shared]);
        $container->register('excluded', 'ExcludedClass')->setArguments([$shared])->addTag('container.excluded');

        $dump = $this->dump($container);

        $this->assertSame(['%env(FOO)%'], $dump->getDefinition('a')->getArgument(0)->getArguments());
        $this->assertSame($dump->getDefinition('a')->getArgument(0), $dump->getDefinition('excluded')->getArgument(0));
    }

    public function testSerializedDumpResolvesEnvPlaceholdersSharedWithInstanceofConditionals()
    {
        $container = $this->createContainer();
        $shared = new Definition('SharedClass', [$container->getParameterBag()->get('env(FOO)')]);
        $container->register('a', 'AClass')
            ->setArguments([$shared])
            ->setInstanceofConditionals(['AInterface' => (new ChildDefinition(''))->setArguments([$shared])]);

        $a = $this->dump($container)->getDefinition('a');

        $this->assertSame(['%env(FOO)%'], $a->getArgument(0)->getArguments());
        $this->assertSame($a->getArgument(0), $a->getInstanceofConditionals()['AInterface']->getArgument(0));
    }

    public function testEnvVarsResolvedWhileCompilingAreReportedAsInlined()
    {
        $_ENV['DUMP_NODE'] = 'a';
        $_ENV['DUMP_EXT'] = 'b';
        $_ENV['DUMP_DYNAMIC'] = 'c';
        $_ENV['DUMP_DROPPED'] = 'd';

        $container = new ContainerBuilder();
        $container->setParameter('debug.container.dump', $this->tempDir.'/container.xml');
        $container->registerExtension(new DebugDumpExtension());
        $container->loadFromExtension('debug_dump', [
            'node_inlined' => '%env(DUMP_NODE)%',
            'ext_inlined' => '%env(DUMP_EXT)%',
            'dynamic' => '%env(DUMP_DYNAMIC)%',
            'dropped' => '%env(DUMP_DROPPED)%',
        ]);
        $container->register('runtime', \stdClass::class)->setArguments(['%env(DUMP_NODE)%'])->setPublic(true);
        $container->addCompilerPass(new ContainerBuilderDebugDumpPass(), PassConfig::TYPE_BEFORE_REMOVING, -255);

        try {
            $container->compile();
        } finally {
            unset($_ENV['DUMP_NODE'], $_ENV['DUMP_EXT'], $_ENV['DUMP_DYNAMIC'], $_ENV['DUMP_DROPPED']);
        }

        $referenced = $container->getParameter('.debug.container.env_vars');
        $inlined = $container->getParameter('.debug.container.inlined_env_vars');
        sort($referenced);
        sort($inlined);

        $this->assertSame(['DUMP_DROPPED', 'DUMP_DYNAMIC', 'DUMP_EXT', 'DUMP_NODE'], $referenced);
        $this->assertSame(['DUMP_EXT', 'DUMP_NODE'], $inlined);
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('debug.container.dump', $this->tempDir.'/container.xml');

        return $container;
    }

    private function dump(ContainerBuilder $container): ContainerBuilder
    {
        $originalDefinitions = serialize($container->getDefinitions());

        (new ContainerBuilderDebugDumpPass())->process($container);

        $this->assertSame($originalDefinitions, serialize($container->getDefinitions()));

        return unserialize(file_get_contents($this->tempDir.'/container.ser'));
    }
}

class DebugDumpConfiguration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('debug_dump');
        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('node_inlined')->inlineEnvVars()->end()
                ->scalarNode('ext_inlined')->end()
                ->scalarNode('dynamic')->end()
                ->scalarNode('dropped')->end()
            ->end();

        return $treeBuilder;
    }
}

class DebugDumpExtension extends Extension
{
    public function getAlias(): string
    {
        return 'debug_dump';
    }

    public function getConfiguration(array $config, ContainerBuilder $container): ?ConfigurationInterface
    {
        return new DebugDumpConfiguration();
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration($this->getConfiguration($configs, $container), $configs);

        $container->setParameter('debug_dump.node', $config['node_inlined']);
        $container->setParameter('debug_dump.ext', strtoupper($container->resolveEnvPlaceholders($config['ext_inlined'], true)));
        $container->register('debug_dump.service', \stdClass::class)->setArguments([$config['dynamic']])->setPublic(true);
    }
}
