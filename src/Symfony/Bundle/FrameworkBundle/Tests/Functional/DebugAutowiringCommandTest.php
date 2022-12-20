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

use Symfony\Bundle\FrameworkBundle\Command\DebugAutowiringCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Console\Tester\CommandCompletionTester;

/**
 * @group functional
 */
class DebugAutowiringCommandTest extends AbstractWebTestCase
{
    public function testBasicFunctionality()
    {
        self::bootKernel(['test_case' => 'ContainerDebug', 'root_config' => 'config.yml']);

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'debug:autowiring']);

        self::assertStringContainsString('Symfony\Component\HttpKernel\HttpKernelInterface', $tester->getDisplay());
        self::assertStringContainsString('(http_kernel)', $tester->getDisplay());
    }

    public function testSearchArgument()
    {
        self::bootKernel(['test_case' => 'ContainerDebug', 'root_config' => 'config.yml']);

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'debug:autowiring', 'search' => 'kern']);

        self::assertStringContainsString('Symfony\Component\HttpKernel\HttpKernelInterface', $tester->getDisplay());
        self::assertStringNotContainsString('Symfony\Component\Routing\RouterInterface', $tester->getDisplay());
    }

    public function testSearchIgnoreBackslashWhenFindingService()
    {
        self::bootKernel(['test_case' => 'ContainerDebug', 'root_config' => 'config.yml']);

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'debug:autowiring', 'search' => 'HttpKernelHttpKernelInterface']);
        self::assertStringContainsString('Symfony\Component\HttpKernel\HttpKernelInterface', $tester->getDisplay());
    }

    public function testSearchNoResults()
    {
        self::bootKernel(['test_case' => 'ContainerDebug', 'root_config' => 'config.yml']);

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'debug:autowiring', 'search' => 'foo_fake'], ['capture_stderr_separately' => true]);

        self::assertStringContainsString('No autowirable classes or interfaces found matching "foo_fake"', $tester->getErrorOutput());
        self::assertEquals(1, $tester->getStatusCode());
    }

    public function testSearchNotAliasedService()
    {
        self::bootKernel(['test_case' => 'ContainerDebug', 'root_config' => 'config.yml']);

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'debug:autowiring', 'search' => 'redirect']);

        self::assertStringContainsString(' more concrete service would be displayed when adding the "--all" option.', $tester->getDisplay());
    }

    public function testSearchNotAliasedServiceWithAll()
    {
        self::bootKernel(['test_case' => 'ContainerDebug', 'root_config' => 'config.yml']);

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'debug:autowiring', 'search' => 'redirect', '--all' => true]);
        self::assertStringContainsString('Pro-tip: use interfaces in your type-hints instead of classes to benefit from the dependency inversion principle.', $tester->getDisplay());
    }

    public function testNotConfusedByClassAliases()
    {
        self::bootKernel(['test_case' => 'ContainerDebug', 'root_config' => 'config.yml']);

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'debug:autowiring', 'search' => 'ClassAlias']);
        self::assertStringContainsString('Symfony\Bundle\FrameworkBundle\Tests\Fixtures\ClassAliasExampleClass', $tester->getDisplay());
    }

    /**
     * @dataProvider provideCompletionSuggestions
     */
    public function testComplete(array $input, array $expectedSuggestions)
    {
        $kernel = self::bootKernel(['test_case' => 'ContainerDebug', 'root_config' => 'config.yml']);
        $command = (new Application($kernel))->add(new DebugAutowiringCommand());

        $tester = new CommandCompletionTester($command);

        $suggestions = $tester->complete($input);

        foreach ($expectedSuggestions as $expectedSuggestion) {
            self::assertContains($expectedSuggestion, $suggestions);
        }
    }

    public function provideCompletionSuggestions(): \Generator
    {
        yield 'search' => [[''], ['SessionHandlerInterface', 'Psr\\Log\\LoggerInterface', 'Psr\\Container\\ContainerInterface $parameterBag']];
    }
}
