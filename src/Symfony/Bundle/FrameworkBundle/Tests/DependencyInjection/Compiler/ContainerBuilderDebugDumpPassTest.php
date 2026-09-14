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
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\Filesystem\Filesystem;

class ContainerBuilderDebugDumpPassTest extends TestCase
{
    public function testEnvVarsThatNothingReadsAtRuntimeAreReportedAsInlined()
    {
        $_ENV['DUMP_NODE'] = 'a';
        $_ENV['DUMP_EXT'] = 'b';
        $_ENV['DUMP_DYNAMIC'] = 'c';

        $dir = sys_get_temp_dir().'/sf_debug_dump_'.bin2hex(random_bytes(6));
        $container = new ContainerBuilder();
        $container->setParameter('debug.container.dump', $dir.'/container.xml');
        $container->registerExtension(new DebugDumpExtension());
        $container->loadFromExtension('debug_dump', [
            'node_inlined' => '%env(DUMP_NODE)%',
            'ext_inlined' => '%env(DUMP_EXT)%',
            'dynamic' => '%env(DUMP_DYNAMIC)%',
        ]);
        $container->addCompilerPass(new ContainerBuilderDebugDumpPass(), PassConfig::TYPE_BEFORE_REMOVING, -255);

        try {
            $container->compile();

            $referenced = $container->getParameter('.debug.container.env_vars');
            $inlined = $container->getParameter('.debug.container.inlined_env_vars');
        } finally {
            unset($_ENV['DUMP_NODE'], $_ENV['DUMP_EXT'], $_ENV['DUMP_DYNAMIC']);
            (new Filesystem())->remove($dir);
        }

        sort($referenced);
        sort($inlined);

        $this->assertSame(['DUMP_DYNAMIC', 'DUMP_EXT', 'DUMP_NODE'], $referenced);
        // the node declares it, and the extension resolves the other one itself; only the third
        // one is still read when the container runs
        $this->assertSame(['DUMP_EXT', 'DUMP_NODE'], $inlined);
    }
}

class DebugDumpConfiguration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('debug_dump');
        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('node_inlined')->attribute('inline_env_vars', true)->end()
                ->scalarNode('ext_inlined')->end()
                ->scalarNode('dynamic')->end()
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
