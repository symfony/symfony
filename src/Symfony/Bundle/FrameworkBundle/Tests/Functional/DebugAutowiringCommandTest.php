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
use Symfony\Component\Console\Tester\ApplicationTester;

/**
 * @group functional
 */
class DebugAutowiringCommandTest extends AbstractWebTestCase
{
    public function testBasicFunctionality()
    {
        static::bootKernel(['test_case' => 'ContainerDebug', 'root_config' => 'config.yml']);

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'debug:autowiring']);

        $this->assertStringContainsString('Symfony\Component\HttpKernel\HttpKernelInterface', $tester->getDisplay());
        $this->assertStringContainsString('(http_kernel)', $tester->getDisplay());
    }

    public function testSearchArgument()
    {
        static::bootKernel(['test_case' => 'ContainerDebug', 'root_config' => 'config.yml']);

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'debug:autowiring', 'search' => 'kern']);

        $this->assertStringContainsString('Symfony\Component\HttpKernel\HttpKernelInterface', $tester->getDisplay());
        $this->assertStringNotContainsString('Symfony\Component\Routing\RouterInterface', $tester->getDisplay());
    }

    public function testSearchIgnoreBackslashWhenFindingService()
    {
        static::bootKernel(['test_case' => 'ContainerDebug', 'root_config' => 'config.yml']);

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'debug:autowiring', 'search' => 'HttpKernelHttpKernelInterface']);
        $this->assertStringContainsString('Symfony\Component\HttpKernel\HttpKernelInterface', $tester->getDisplay());
    }

    public function testSearchNoResults()
    {
        static::bootKernel(['test_case' => 'ContainerDebug', 'root_config' => 'config.yml']);

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'debug:autowiring', 'search' => 'foo_fake'], ['capture_stderr_separately' => true]);

        $this->assertStringContainsString('No autowirable classes or interfaces found matching "foo_fake"', $tester->getErrorOutput());
        $this->assertEquals(1, $tester->getStatusCode());
    }

    public function testSearchNotAliasedService()
    {
        static::bootKernel(['test_case' => 'ContainerDebug', 'root_config' => 'config.yml']);

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'debug:autowiring', 'search' => 'redirect']);

        $this->assertStringContainsString(' more concrete service would be displayed when adding the "--all" option.', $tester->getDisplay());
    }

    public function testSearchNotAliasedServiceWithAll()
    {
        static::bootKernel(['test_case' => 'ContainerDebug', 'root_config' => 'config.yml']);

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'debug:autowiring', 'search' => 'redirect', '--all' => true]);
        $this->assertStringContainsString('Pro-tip: use interfaces in your type-hints instead of classes to benefit from the dependency inversion principle.', $tester->getDisplay());
    }
}
