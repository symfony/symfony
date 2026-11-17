<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Bundle\FrameworkBundle\Command\ConfigDumpReferenceCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\Kernel;

#[Group('functional')]
class ConfigDumpReferenceCommandTest extends AbstractWebTestCase
{
    #[TestWith([true])]
    #[TestWith([false])]
    public function testShowList(bool $debug)
    {
        $tester = $this->createCommandTester($debug);
        $ret = $tester->execute([]);

        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $this->assertStringContainsString('Available registered bundles with their extension alias if available', $tester->getDisplay());
        $this->assertStringContainsString('  DefaultConfigTestBundle            default_config_test', $tester->getDisplay());
        $this->assertStringContainsString('  ExtensionWithoutConfigTestBundle   extension_without_config_test', $tester->getDisplay());
        $this->assertStringContainsString('  FrameworkBundle                    framework', $tester->getDisplay());
        $this->assertStringContainsString('  TestBundle                         test', $tester->getDisplay());
        $this->assertStringContainsString('Available registered non-bundle extension aliases', $tester->getDisplay());
        $this->assertStringContainsString('  foo', $tester->getDisplay());
        $this->assertStringContainsString('  test_dump', $tester->getDisplay());
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function testDumpKernelExtension(bool $debug)
    {
        $tester = $this->createCommandTester($debug);
        $ret = $tester->execute(['name' => 'foo']);

        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $this->assertStringContainsString('foo:', $tester->getDisplay());
        $this->assertStringContainsString('    bar', $tester->getDisplay());
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function testDumpBundleName(bool $debug)
    {
        $tester = $this->createCommandTester($debug);
        $ret = $tester->execute(['name' => 'TestBundle']);

        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $this->assertStringContainsString('test:', $tester->getDisplay());
        $this->assertStringContainsString('    custom:', $tester->getDisplay());
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function testDumpExtensionConfigWithoutBundle(bool $debug)
    {
        $tester = $this->createCommandTester($debug);
        $ret = $tester->execute(['name' => 'test_dump']);

        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $this->assertStringContainsString('enabled:              true', $tester->getDisplay());
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function testDumpAtPath(bool $debug)
    {
        $tester = $this->createCommandTester($debug);
        $ret = $tester->execute([
            'name' => 'test',
            'path' => 'array',
        ]);

        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $this->assertSame(<<<'EOL'
            # Default configuration for extension with alias: "test" at path "array"
            array:
                child1:               ~
                child2:               ~


            EOL,
            $tester->getDisplay(true)
        );
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function testDumpAtPathXml(bool $debug)
    {
        $tester = $this->createCommandTester($debug);
        $ret = $tester->execute([
            'name' => 'test',
            'path' => 'array',
            '--format' => 'xml',
        ]);

        $this->assertSame(1, $ret);
        $this->assertStringContainsString('[ERROR] The "path" option is only available for the "yaml" format.', $tester->getDisplay());
    }

    #[TestWith(['yaml'])]
    #[TestWith(['xml'])]
    public function testDumpFrameworkBundle(string $format)
    {
        $tester = $this->createCommandTester(true);
        $ret = $tester->execute(['name' => 'framework', '--format' => $format]);

        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $this->assertStringContainsString('%env(default::SYMFONY_TRUSTED_PROXIES)%', $tester->getDisplay());
    }

    #[TestWith(['yaml', [
        '    # <info>hint</info></error>',
        "    value:                '<info>tag</info></error>'",
    ]])]
    #[TestWith(['xml', [
        '<!-- value: <info>hint</info></error> -->',
        '<config value="<info>tag</info></error>" />',
    ]])]
    public function testDumpReferenceWithMarkup(string $format, array $expectedLines)
    {
        $extension = new class extends Extension implements ConfigurationInterface {
            public function getAlias(): string
            {
                return 'markup_test';
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getConfiguration(array $config, ContainerBuilder $container): ?ConfigurationInterface
            {
                return $this;
            }

            public function getConfigTreeBuilder(): TreeBuilder
            {
                $treeBuilder = new TreeBuilder('markup_test');
                $treeBuilder->getRootNode()
                    ->children()
                        ->scalarNode('value')
                            ->defaultValue('<info>tag</info></error>')
                            ->info('<info>hint</info></error>')
                        ->end()
                    ->end();

                return $treeBuilder;
            }
        };

        $bundle = new class($extension) extends Bundle {
            public function __construct(private Extension $markupExtension)
            {
                $this->name = 'MarkupTestBundle';
            }

            public function getContainerExtension(): Extension
            {
                return $this->markupExtension;
            }
        };

        $kernel = new class($bundle) extends Kernel {
            public function __construct(private Bundle $markupBundle)
            {
                parent::__construct('test', false);
            }

            public function boot(): void
            {
                $this->booted = true;
                $this->container = new ContainerBuilder();
                $this->initializeBundles();
            }

            public function registerBundles(): iterable
            {
                return [$this->markupBundle];
            }

            public function registerContainerConfiguration(LoaderInterface $loader): void
            {
            }

            protected function initializeBundles(): void
            {
                $this->bundles = ['MarkupTestBundle' => $this->markupBundle];
            }

            protected function buildContainer(): ContainerBuilder
            {
                $container = new ContainerBuilder();
                $container->registerExtension($this->markupBundle->getContainerExtension());

                return $container;
            }
        };

        $application = new Application($kernel);
        $application->addCommand(new ConfigDumpReferenceCommand());

        $tester = new CommandTester($application->find('config:dump-reference'));
        $ret = $tester->execute(['name' => 'markup_test', '--format' => $format], ['decorated' => true]);

        $this->assertSame(0, $ret);
        foreach ($expectedLines as $expected) {
            $this->assertStringContainsString($expected, $tester->getDisplay());
        }
    }

    #[DataProvider('provideCompletionSuggestions')]
    public function testComplete(bool $debug, array $input, array $expectedSuggestions)
    {
        $application = $this->createApplication($debug);

        $application->addCommand(new ConfigDumpReferenceCommand());
        $tester = new CommandCompletionTester($application->get('config:dump-reference'));
        $suggestions = $tester->complete($input);
        $this->assertSame($expectedSuggestions, $suggestions);
    }

    public static function provideCompletionSuggestions(): iterable
    {
        $name = ['foo', 'default_config_test', 'extension_without_config_test', 'framework', 'test', 'test_dump', 'DefaultConfigTestBundle', 'ExtensionWithoutConfigTestBundle', 'FrameworkBundle', 'ServicesInBuildTestBundle', 'TestBundle'];
        yield 'name, no debug' => [false, [''], $name];
        yield 'name, debug' => [true, [''], $name];

        $optionFormat = ['yaml', 'xml'];
        yield 'option --format, no debug' => [false, ['--format', ''], $optionFormat];
        yield 'option --format, debug' => [true, ['--format', ''], $optionFormat];
    }

    private function createCommandTester(bool $debug): CommandTester
    {
        $command = $this->createApplication($debug)->find('config:dump-reference');

        return new CommandTester($command);
    }

    private function createApplication(bool $debug): Application
    {
        $kernel = static::createKernel(['debug' => $debug, 'test_case' => 'ConfigDump', 'root_config' => 'config.yml']);
        $application = new Application($kernel);
        $application->doRun(new ArrayInput([]), new NullOutput());

        return $application;
    }
}
