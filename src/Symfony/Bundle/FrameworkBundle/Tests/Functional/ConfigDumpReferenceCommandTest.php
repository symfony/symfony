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
    private $application;

    protected function setUp(): void
    {
        $kernel = self::createKernel(['test_case' => 'ConfigDump', 'root_config' => 'config.yml']);
        $this->application = new Application($kernel);
        $this->application->doRun(new ArrayInput([]), new NullOutput());
    }

    public function testDumpKernelExtension()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(['name' => 'foo']);
        self::assertStringContainsString('foo:', $tester->getDisplay());
        self::assertStringContainsString('    bar', $tester->getDisplay());
    }

    public function testDumpBundleName()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(['name' => 'TestBundle']);

        self::assertSame(0, $ret, 'Returns 0 in case of success');
        self::assertStringContainsString('test:', $tester->getDisplay());
        self::assertStringContainsString('    custom:', $tester->getDisplay());
    }

    public function testDumpExtensionConfigWithoutBundle()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(['name' => 'test_dump']);

        self::assertSame(0, $ret, 'Returns 0 in case of success');
        self::assertStringContainsString('enabled:              true', $tester->getDisplay());
    }

    public function testDumpAtPath()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute([
            'name' => 'test',
            'path' => 'array',
        ]);

        self::assertSame(0, $ret, 'Returns 0 in case of success');
        self::assertSame(<<<'EOL'
# Default configuration for extension with alias: "test" at path "array"
array:
    child1:               ~
    child2:               ~


EOL, $tester->getDisplay(true));
    }

    public function testDumpAtPathXml()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute([
            'name' => 'test',
            'path' => 'array',
            '--format' => 'xml',
        ]);

        self::assertSame(1, $ret);
        self::assertStringContainsString('[ERROR] The "path" option is only available for the "yaml" format.', $tester->getDisplay());
    }

    /**
     * @dataProvider provideCompletionSuggestions
     */
    public function testComplete(array $input, array $expectedSuggestions)
    {
        $this->application->add(new ConfigDumpReferenceCommand());
        $tester = new CommandCompletionTester($this->application->get('config:dump-reference'));
        $suggestions = $tester->complete($input, 2);
        self::assertSame($expectedSuggestions, $suggestions);
    }

    public function provideCompletionSuggestions(): iterable
    {
        yield 'name' => [[''], ['DefaultConfigTestBundle', 'default_config_test', 'ExtensionWithoutConfigTestBundle', 'extension_without_config_test', 'FrameworkBundle', 'framework', 'TestBundle', 'test']];
        yield 'option --format' => [['--format', ''], ['yaml', 'xml']];
    }

    private function createCommandTester(): CommandTester
    {
        $command = $this->application->find('config:dump-reference');

        return new CommandTester($command);
    }
}
