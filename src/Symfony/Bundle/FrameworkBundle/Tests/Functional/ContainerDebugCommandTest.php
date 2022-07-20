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

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\BackslashClass;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Console\Tester\CommandCompletionTester;

/**
 * @group functional
 */
class ContainerDebugCommandTest extends AbstractWebTestCase
{
    public function testDumpContainerIfNotExists()
    {
        static::bootKernel(['test_case' => 'ContainerDebug', 'root_config' => 'config.yml', 'debug' => true]);

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        @unlink(static::getContainer()->getParameter('debug.container.dump'));

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'debug:container']);

        $this->assertFileExists(static::getContainer()->getParameter('debug.container.dump'));
    }

    public function testNoDebug()
    {
        static::bootKernel(['test_case' => 'ContainerDebug', 'root_config' => 'config.yml', 'debug' => false]);

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'debug:container']);

        $this->assertStringContainsString('public', $tester->getDisplay());
    }

    public function testPrivateAlias()
    {
        static::bootKernel(['test_case' => 'ContainerDebug', 'root_config' => 'config.yml']);

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'debug:container', '--show-hidden' => true]);
        $this->assertStringNotContainsString('public', $tester->getDisplay());
        $this->assertStringNotContainsString('private_alias', $tester->getDisplay());

        $tester->run(['command' => 'debug:container']);
        $this->assertStringContainsString('public', $tester->getDisplay());
        $this->assertStringContainsString('private_alias', $tester->getDisplay());

        $tester->run(['command' => 'debug:container', 'name' => 'private_alias']);
        $this->assertStringContainsString('The "private_alias" service or alias has been removed', $tester->getDisplay());
    }

    /**
     * @dataProvider provideIgnoreBackslashWhenFindingService
     */
    public function testIgnoreBackslashWhenFindingService(string $validServiceId)
    {
        static::bootKernel(['test_case' => 'ContainerDebug', 'root_config' => 'config.yml']);

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'debug:container', 'name' => $validServiceId]);
        $this->assertStringNotContainsString('No services found', $tester->getDisplay());
    }

    public function testDescribeEnvVars()
    {
        putenv('REAL=value');
        static::bootKernel(['test_case' => 'ContainerDebug', 'root_config' => 'config.yml', 'debug' => true]);

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        @unlink(static::getContainer()->getParameter('debug.container.dump'));

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'debug:container', '--env-vars' => true], ['decorated' => false]);

        $this->assertStringMatchesFormat(<<<'TXT'

Symfony Container Environment Variables
=======================================

 --------- ----------------- ------------%w
  Name      Default value     Real value%w
 --------- ----------------- ------------%w
  JSON      "[1, "2.5", 3]"   n/a%w
  REAL      n/a               "value"%w
  UNKNOWN   n/a               n/a%w
 --------- ----------------- ------------%w

 // Note real values might be different between web and CLI.%w

 [WARNING] The following variables are missing:%w

 * UNKNOWN

TXT
            , $tester->getDisplay(true));

        putenv('REAL');
    }

    public function testDescribeEnvVar()
    {
        static::bootKernel(['test_case' => 'ContainerDebug', 'root_config' => 'config.yml', 'debug' => true]);

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        @unlink(static::getContainer()->getParameter('debug.container.dump'));

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'debug:container', '--env-var' => 'js'], ['decorated' => false]);

        $this->assertStringContainsString(file_get_contents(__DIR__.'/Fixtures/describe_env_vars.txt'), $tester->getDisplay(true));
    }

    public function testGetDeprecation()
    {
        static::bootKernel(['test_case' => 'ContainerDebug', 'root_config' => 'config.yml', 'debug' => true]);
        $path = sprintf('%s/%sDeprecations.log', static::$kernel->getContainer()->getParameter('kernel.build_dir'), static::$kernel->getContainer()->getParameter('kernel.container_class'));
        touch($path);
        file_put_contents($path, serialize([[
            'type' => 16384,
            'message' => 'The "Symfony\Bundle\FrameworkBundle\Controller\Controller" class is deprecated since Symfony 4.2, use Symfony\Bundle\FrameworkBundle\Controller\AbstractController instead.',
            'file' => '/home/hamza/projet/contrib/sf/vendor/symfony/framework-bundle/Controller/Controller.php',
            'line' => 17,
            'trace' => [[
                'file' => '/home/hamza/projet/contrib/sf/src/Controller/DefaultController.php',
                'line' => 9,
                'function' => 'spl_autoload_call',
            ]],
            'count' => 1,
        ]]));
        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        @unlink(static::getContainer()->getParameter('debug.container.dump'));

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'debug:container', '--deprecations' => true]);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('Symfony\Bundle\FrameworkBundle\Controller\Controller', $tester->getDisplay());
        $this->assertStringContainsString('/home/hamza/projet/contrib/sf/vendor/symfony/framework-bundle/Controller/Controller.php', $tester->getDisplay());
    }

    public function testGetDeprecationNone()
    {
        static::bootKernel(['test_case' => 'ContainerDebug', 'root_config' => 'config.yml', 'debug' => true]);
        $path = sprintf('%s/%sDeprecations.log', static::$kernel->getContainer()->getParameter('kernel.build_dir'), static::$kernel->getContainer()->getParameter('kernel.container_class'));
        touch($path);
        file_put_contents($path, serialize([]));

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        @unlink(static::getContainer()->getParameter('debug.container.dump'));

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'debug:container', '--deprecations' => true]);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('[OK] There are no deprecations in the logs!', $tester->getDisplay());
    }

    public function testGetDeprecationNoFile()
    {
        static::bootKernel(['test_case' => 'ContainerDebug', 'root_config' => 'config.yml', 'debug' => true]);
        $path = sprintf('%s/%sDeprecations.log', static::$kernel->getContainer()->getParameter('kernel.build_dir'), static::$kernel->getContainer()->getParameter('kernel.container_class'));
        @unlink($path);

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        @unlink(static::getContainer()->getParameter('debug.container.dump'));

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'debug:container', '--deprecations' => true]);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('[WARNING] The deprecation file does not exist', $tester->getDisplay());
    }

    public function provideIgnoreBackslashWhenFindingService()
    {
        return [
            [BackslashClass::class],
            ['FixturesBackslashClass'],
            ['\\'.BackslashClass::class],
        ];
    }

    /**
     * @dataProvider provideCompletionSuggestions
     */
    public function testComplete(array $input, array $expectedSuggestions, array $notExpectedSuggestions = [])
    {
        static::bootKernel(['test_case' => 'ContainerDebug', 'root_config' => 'config.yml', 'debug' => true]);

        $application = new Application(static::$kernel);
        $tester = new CommandCompletionTester($application->find('debug:container'));
        $suggestions = $tester->complete($input);

        foreach ($expectedSuggestions as $expectedSuggestion) {
            $this->assertContains($expectedSuggestion, $suggestions);
        }
        foreach ($notExpectedSuggestions as $notExpectedSuggestion) {
            $this->assertNotContains($notExpectedSuggestion, $suggestions);
        }
    }

    public function provideCompletionSuggestions()
    {
        $serviceId = 'console.command.container_debug';
        $hiddenServiceId = '.console.command.container_debug.lazy';
        $interfaceServiceId = 'Symfony\Component\HttpKernel\HttpKernelInterface';

        yield 'name' => [
            [''],
            [$serviceId, $interfaceServiceId],
            [$hiddenServiceId],
        ];

        yield 'name (with hidden)' => [
            ['--show-hidden', ''],
            [$serviceId, $interfaceServiceId, $hiddenServiceId],
        ];

        yield 'name (with current value)' => [
            ['--show-hidden', 'console'],
            [$serviceId, $hiddenServiceId],
            [$interfaceServiceId],
        ];

        yield 'name (no suggestion with --tags)' => [
            ['--tags', ''],
            [],
            [$serviceId, $interfaceServiceId, $hiddenServiceId],
        ];

        yield 'option --tag' => [
            ['--tag', ''],
            ['console.command'],
        ];

        yield 'option --parameter' => [
            ['--parameter', ''],
            ['kernel.debug'],
        ];

        yield 'option --format' => [
            ['--format', ''],
            ['txt', 'xml', 'json', 'md'],
        ];
    }
}
