<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DebugBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\DebugBundle\DebugBundle;
use Symfony\Bundle\DebugBundle\DependencyInjection\DebugExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\VarDumper\Caster\ReflectionCaster;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Server\Connection;
use Symfony\Component\VarDumper\Server\DumpServer;

class DebugExtensionTest extends TestCase
{
    public function testLoadWithoutConfiguration()
    {
        $container = $this->createContainer();
        $container->registerExtension(new DebugExtension());
        $container->loadFromExtension('debug', []);
        $this->compileContainer($container);

        $expectedTags = [
            [
                'id' => 'dump',
                'template' => '@Debug/Profiler/dump.html.twig',
                'priority' => 240,
            ],
        ];

        $this->assertSame($expectedTags, $container->getDefinition('data_collector.dump')->getTag('data_collector'));
    }

    public function testUnsetClosureFileInfoShouldBeRegisteredInVarCloner()
    {
        $container = $this->createContainer();
        $container->registerExtension(new DebugExtension());
        $container->loadFromExtension('debug', []);
        $this->compileContainer($container);

        $definition = $container->getDefinition('var_dumper.cloner');

        $called = false;
        foreach ($definition->getMethodCalls() as $call) {
            if ('addCasters' !== $call[0]) {
                continue;
            }

            $argument = $call[1][0] ?? null;
            if (null === $argument) {
                continue;
            }

            if (['Closure' => ReflectionCaster::class.'::unsetClosureFileInfo'] === $argument) {
                $called = true;
                break;
            }
        }

        $this->assertTrue($called);
    }

    public static function provideServicesUsingDumpDestinationCreation(): array
    {
        return [
            ['tcp://localhost:1234', 'tcp://localhost:1234', null],
            [null, '', null],
            ['php://stderr', '', 'php://stderr'],
        ];
    }

    /**
     * @dataProvider provideServicesUsingDumpDestinationCreation
     */
    public function testServicesUsingDumpDestinationCreation(?string $dumpDestination, string $expectedHost, ?string $expectedOutput)
    {
        $container = $this->createContainer();
        $container->registerExtension(new DebugExtension());
        $container->loadFromExtension('debug', ['dump_destination' => $dumpDestination]);
        $container->setAlias('dump_server_public', 'var_dumper.dump_server')->setPublic(true);
        $container->setAlias('server_conn_public', 'var_dumper.server_connection')->setPublic(true);
        $container->setAlias('cli_dumper_public', 'var_dumper.cli_dumper')->setPublic(true);
        $container->register('request_stack', RequestStack::class);
        $this->compileContainer($container);

        $dumpServer = $container->get('dump_server_public');
        $this->assertInstanceOf(DumpServer::class, $dumpServer);
        $this->assertSame($expectedHost, $container->findDefinition('dump_server_public')->getArgument(0));

        $serverConn = $container->get('server_conn_public');
        $this->assertInstanceOf(Connection::class, $serverConn);
        $this->assertSame($expectedHost, $container->findDefinition('server_conn_public')->getArgument(0));

        $cliDumper = $container->get('cli_dumper_public');
        $this->assertInstanceOf(CliDumper::class, $cliDumper);
        $this->assertSame($expectedOutput, $container->findDefinition('cli_dumper_public')->getArgument(0));
    }

    private function createContainer()
    {
        $container = new ContainerBuilder(new ParameterBag([
            'kernel.cache_dir' => __DIR__,
            'kernel.build_dir' => __DIR__,
            'kernel.charset' => 'UTF-8',
            'kernel.debug' => true,
            'kernel.project_dir' => __DIR__,
            'kernel.bundles' => ['DebugBundle' => DebugBundle::class],
        ]));

        return $container;
    }

    private function compileContainer(ContainerBuilder $container)
    {
        $container->getCompilerPassConfig()->setOptimizationPasses([]);
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->getCompilerPassConfig()->setAfterRemovingPasses([]);
        $container->compile();
    }
}
