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
        $this->assertStringContainsString('    foo: bar', $tester->getDisplay());
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
        $this->assertStringContainsString('custom: foo', $tester->getDisplay());
    }

    /**
     * @testWith [true]
     *           [false]
     */
    public function testDumpBundleOption(bool $debug)
    {
        $tester = $this->createCommandTester($debug);
        $ret = $tester->execute(['name' => 'TestBundle', 'path' => 'custom']);

        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $this->assertStringContainsString('foo', $tester->getDisplay());
    }

    /**
     * @testWith [true]
     *           [false]
     */
    public function testDumpWithoutTitleIsValidJson(bool $debug)
    {
        $tester = $this->createCommandTester($debug);
        $ret = $tester->execute(['name' => 'TestBundle', '--format' => 'json']);

        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $this->assertJson($tester->getDisplay());
    }

    /**
     * @testWith [true]
     *           [false]
     */
    public function testDumpWithUnsupportedFormat(bool $debug)
    {
        $tester = $this->createCommandTester($debug);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Supported formats are "txt", "yaml", "json"');

        $tester->execute([
            'name' => 'test',
            '--format' => 'xml',
        ]);
    }

    /**
     * @testWith [true]
     *           [false]
     */
    public function testParametersValuesAreResolved(bool $debug)
    {
        $tester = $this->createCommandTester($debug);
        $ret = $tester->execute(['name' => 'framework']);

        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $this->assertStringContainsString("locale: '%env(LOCALE)%'", $tester->getDisplay());
        $this->assertStringContainsString('secret: test', $tester->getDisplay());
    }

    /**
     * @testWith [true]
     *           [false]
     */
    public function testParametersValuesAreFullyResolved(bool $debug)
    {
        $tester = $this->createCommandTester($debug);
        $ret = $tester->execute(['name' => 'framework', '--resolve-env' => true]);

        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $this->assertStringContainsString('locale: en', $tester->getDisplay());
        $this->assertStringContainsString('secret: test', $tester->getDisplay());
        $this->assertStringContainsString('cookie_httponly: true', $tester->getDisplay());
        $this->assertStringContainsString('ide: '.$debug ? ($_ENV['SYMFONY_IDE'] ?? $_SERVER['SYMFONY_IDE'] ?? 'null') : 'null', $tester->getDisplay());
    }

    /**
     * @testWith [true]
     *           [false]
     */
    public function testDefaultParameterValueIsResolvedIfConfigIsExisting(bool $debug)
    {
        $tester = $this->createCommandTester($debug);
        $ret = $tester->execute(['name' => 'framework']);

        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $kernelCacheDir = self::$kernel->getContainer()->getParameter('kernel.cache_dir');
        $this->assertStringContainsString(\sprintf("dsn: 'file:%s/profiler'", $kernelCacheDir), $tester->getDisplay());
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
        $this->assertStringContainsString('enabled: true', $tester->getDisplay());
    }

    /**
     * @testWith [true]
     *           [false]
     */
    public function testDumpUndefinedBundleOption(bool $debug)
    {
        $tester = $this->createCommandTester($debug);
        $tester->execute(['name' => 'TestBundle', 'path' => 'foo']);

        $this->assertStringContainsString('Unable to find configuration for "test.foo"', $tester->getDisplay());
    }

    /**
     * @testWith [true]
     *           [false]
     */
    public function testDumpWithPrefixedEnv(bool $debug)
    {
        $tester = $this->createCommandTester($debug);
        $tester->execute(['name' => 'FrameworkBundle']);

        $this->assertStringContainsString("cookie_httponly: '%env(bool:COOKIE_HTTPONLY)%'", $tester->getDisplay());
    }

    /**
     * @testWith [true]
     *           [false]
     */
    public function testDumpFallsBackToDefaultConfigAndResolvesParameterValue(bool $debug)
    {
        $tester = $this->createCommandTester($debug);
        $ret = $tester->execute(['name' => 'DefaultConfigTestBundle']);

        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $this->assertStringContainsString('foo: bar', $tester->getDisplay());
    }

    /**
     * @testWith [true]
     *           [false]
     */
    public function testDumpFallsBackToDefaultConfigAndResolvesEnvPlaceholder(bool $debug)
    {
        $tester = $this->createCommandTester($debug);
        $ret = $tester->execute(['name' => 'DefaultConfigTestBundle']);

        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $this->assertStringContainsString("baz: '%env(BAZ)%'", $tester->getDisplay());
    }

    /**
     * @testWith [true]
     *           [false]
     */
    public function testDumpThrowsExceptionWhenDefaultConfigFallbackIsImpossible(bool $debug)
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The extension with alias "extension_without_config_test" does not have configuration.');

        $tester = $this->createCommandTester($debug);
        $tester->execute(['name' => 'ExtensionWithoutConfigTestBundle']);
    }

    /**
     * @dataProvider provideCompletionSuggestions
     */
    public function testComplete(bool $debug, array $input, array $expectedSuggestions)
    {
        $application = $this->createApplication($debug);

        $application->add(new ConfigDebugCommand());
        $tester = new CommandCompletionTester($application->get('debug:config'));
        $suggestions = $tester->complete($input);

        foreach ($expectedSuggestions as $expectedSuggestion) {
            $this->assertContains($expectedSuggestion, $suggestions);
        }
    }

    public static function provideCompletionSuggestions(): \Generator
    {
        $name = ['default_config_test', 'extension_without_config_test', 'framework', 'test', 'foo', 'test_dump'];
        yield 'name, no debug' => [false, [''], $name];
        yield 'name, debug' => [true, [''], $name];

        $nameWithPath = ['secret', 'router.resource', 'router.utf8', 'router.enabled', 'validation.enabled', 'default_locale'];
        yield 'name with existing path, no debug' => [false, ['framework', ''], $nameWithPath];
        yield 'name with existing path, debug' => [true, ['framework', ''], $nameWithPath];

        yield 'option --format, no debug' => [false, ['--format', ''], ['yaml', 'json']];
        yield 'option --format, debug' => [true, ['--format', ''], ['yaml', 'json']];
    }

    private function createCommandTester(bool $debug): CommandTester
    {
        $command = $this->createApplication($debug)->find('debug:config');

        return new CommandTester($command);
    }

    private function createApplication(bool $debug): Application
    {
        $kernel = static::bootKernel(['debug' => $debug, 'test_case' => 'ConfigDump', 'root_config' => 'config.yml']);
        $application = new Application($kernel);
        $application->doRun(new ArrayInput([]), new NullOutput());

        return $application;
    }
}
