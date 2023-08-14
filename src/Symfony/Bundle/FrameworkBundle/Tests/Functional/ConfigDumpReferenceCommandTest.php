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

use Symfony\Bundle\FrameworkBundle\Command\ConfigDumpReferenceCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @group functional
 */
class ConfigDumpReferenceCommandTest extends AbstractWebTestCase
{
    /**
     * @testWith [true]
     *           [false]
     */
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

    /**
     * @testWith [true]
     *           [false]
     */
    public function testDumpKernelExtension(bool $debug)
    {
        $tester = $this->createCommandTester($debug);
        $ret = $tester->execute(['name' => 'foo']);

        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $this->assertStringContainsString('foo:', $tester->getDisplay());
        $this->assertStringContainsString('    bar', $tester->getDisplay());
    }

    /**
     * @testWith [true]
     *           [false]
     */
    public function testDumpBundleName(bool $debug)
    {
        $tester = $this->createCommandTester($debug);
        $ret = $tester->execute(['name' => 'TestBundle']);

        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $this->assertStringContainsString('test:', $tester->getDisplay());
        $this->assertStringContainsString('    custom:', $tester->getDisplay());
    }

    /**
     * @testWith [true]
     *           [false]
     */
    public function testDumpExtensionConfigWithoutBundle(bool $debug)
    {
        $tester = $this->createCommandTester($debug);
        $ret = $tester->execute(['name' => 'test_dump']);

        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $this->assertStringContainsString('enabled:              true', $tester->getDisplay());
    }

    /**
     * @testWith [true]
     *           [false]
     */
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


EOL
            , $tester->getDisplay(true));
    }

    /**
     * @testWith [true]
     *           [false]
     */
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

    /**
     * @dataProvider provideCompletionSuggestions
     */
    public function testComplete(bool $debug, array $input, array $expectedSuggestions)
    {
        $application = $this->createApplication($debug);

        $application->add(new ConfigDumpReferenceCommand());
        $tester = new CommandCompletionTester($application->get('config:dump-reference'));
        $suggestions = $tester->complete($input);
        $this->assertSame($expectedSuggestions, $suggestions);
    }

    public static function provideCompletionSuggestions(): iterable
    {
        $name = ['foo', 'default_config_test', 'extension_without_config_test', 'framework', 'test', 'test_dump', 'DefaultConfigTestBundle', 'ExtensionWithoutConfigTestBundle', 'FrameworkBundle', 'TestBundle'];
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
