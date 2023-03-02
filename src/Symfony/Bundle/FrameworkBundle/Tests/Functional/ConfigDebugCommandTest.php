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

use Symfony\Bundle\FrameworkBundle\Command\ConfigDebugCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @group functional
 */
class ConfigDebugCommandTest extends AbstractWebTestCase
{
    private $application;

    protected function setUp(): void
    {
        $kernel = static::createKernel(['test_case' => 'ConfigDump', 'root_config' => 'config.yml']);
        $this->application = new Application($kernel);
        $this->application->doRun(new ArrayInput([]), new NullOutput());
    }

    public function testDumpBundleName()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(['name' => 'TestBundle']);

        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $this->assertStringContainsString('custom: foo', $tester->getDisplay());
    }

    public function testDumpBundleOption()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(['name' => 'TestBundle', 'path' => 'custom']);

        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $this->assertStringContainsString('foo', $tester->getDisplay());
    }

    public function testDumpWithUnsupportedFormat()
    {
        $tester = $this->createCommandTester();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Supported formats are "yaml", "json"');

        $tester->execute([
            'name' => 'test',
            '--format' => 'xml',
        ]);
    }

    public function testParametersValuesAreResolved()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(['name' => 'framework']);

        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $this->assertStringContainsString("locale: '%env(LOCALE)%'", $tester->getDisplay());
        $this->assertStringContainsString('secret: test', $tester->getDisplay());
    }

    public function testParametersValuesAreFullyResolved()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(['name' => 'framework', '--resolve-env' => true]);

        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $this->assertStringContainsString('locale: en', $tester->getDisplay());
        $this->assertStringContainsString('secret: test', $tester->getDisplay());
        $this->assertStringContainsString('cookie_httponly: true', $tester->getDisplay());
        $this->assertStringContainsString('ide: '.($_ENV['SYMFONY_IDE'] ?? $_SERVER['SYMFONY_IDE'] ?? 'null'), $tester->getDisplay());
    }

    public function testDefaultParameterValueIsResolvedIfConfigIsExisting()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(['name' => 'framework']);

        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $kernelCacheDir = $this->application->getKernel()->getContainer()->getParameter('kernel.cache_dir');
        $this->assertStringContainsString(sprintf("dsn: 'file:%s/profiler'", $kernelCacheDir), $tester->getDisplay());
    }

    public function testDumpExtensionConfigWithoutBundle()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(['name' => 'test_dump']);

        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $this->assertStringContainsString('enabled: true', $tester->getDisplay());
    }

    public function testDumpUndefinedBundleOption()
    {
        $tester = $this->createCommandTester();
        $tester->execute(['name' => 'TestBundle', 'path' => 'foo']);

        $this->assertStringContainsString('Unable to find configuration for "test.foo"', $tester->getDisplay());
    }

    public function testDumpWithPrefixedEnv()
    {
        $tester = $this->createCommandTester();
        $tester->execute(['name' => 'FrameworkBundle']);

        $this->assertStringContainsString("cookie_httponly: '%env(bool:COOKIE_HTTPONLY)%'", $tester->getDisplay());
    }

    public function testDumpFallsBackToDefaultConfigAndResolvesParameterValue()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(['name' => 'DefaultConfigTestBundle']);

        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $this->assertStringContainsString('foo: bar', $tester->getDisplay());
    }

    public function testDumpFallsBackToDefaultConfigAndResolvesEnvPlaceholder()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(['name' => 'DefaultConfigTestBundle']);

        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $this->assertStringContainsString("baz: '%env(BAZ)%'", $tester->getDisplay());
    }

    public function testDumpThrowsExceptionWhenDefaultConfigFallbackIsImpossible()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The extension with alias "extension_without_config_test" does not have configuration.');

        $tester = $this->createCommandTester();
        $tester->execute(['name' => 'ExtensionWithoutConfigTestBundle']);
    }

    /**
     * @dataProvider provideCompletionSuggestions
     */
    public function testComplete(array $input, array $expectedSuggestions)
    {
        $this->application->add(new ConfigDebugCommand());

        $tester = new CommandCompletionTester($this->application->get('debug:config'));

        $suggestions = $tester->complete($input);

        foreach ($expectedSuggestions as $expectedSuggestion) {
            $this->assertContains($expectedSuggestion, $suggestions);
        }
    }

    public static function provideCompletionSuggestions(): \Generator
    {
        yield 'name' => [[''], ['default_config_test', 'extension_without_config_test', 'framework', 'test']];

        yield 'name (started CamelCase)' => [['Fra'], ['DefaultConfigTestBundle', 'ExtensionWithoutConfigTestBundle', 'FrameworkBundle', 'TestBundle']];

        yield 'name with existing path' => [['framework', ''], ['secret', 'router.resource', 'router.utf8', 'router.enabled', 'validation.enabled', 'default_locale']];

        yield 'option --format' => [['--format', ''], ['yaml', 'json']];
    }

    private function createCommandTester(): CommandTester
    {
        $command = $this->application->find('debug:config');

        return new CommandTester($command);
    }
}
