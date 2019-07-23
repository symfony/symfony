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

        @unlink(static::$container->getParameter('debug.container.dump'));

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'debug:container']);

        $this->assertFileExists(static::$container->getParameter('debug.container.dump'));
    }

    public function testNoDebug()
    {
        static::bootKernel(['test_case' => 'ContainerDebug', 'root_config' => 'config.yml', 'debug' => false]);

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'debug:container']);

        $this->assertContains('public', $tester->getDisplay());
    }

    public function testPrivateAlias()
    {
        static::bootKernel(['test_case' => 'ContainerDebug', 'root_config' => 'config.yml']);

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'debug:container', '--show-hidden' => true]);
        $this->assertNotContains('public', $tester->getDisplay());
        $this->assertNotContains('private_alias', $tester->getDisplay());

        $tester->run(['command' => 'debug:container']);
        $this->assertContains('public', $tester->getDisplay());
        $this->assertContains('private_alias', $tester->getDisplay());
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
        $this->assertNotContains('No services found', $tester->getDisplay());
    }

    public function testDescribeEnvVars()
    {
        putenv('REAL=value');
        static::bootKernel(['test_case' => 'ContainerDebug', 'root_config' => 'config.yml', 'debug' => true]);

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        @unlink(static::$container->getParameter('debug.container.dump'));

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

        @unlink(static::$container->getParameter('debug.container.dump'));

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'debug:container', '--env-var' => 'js'], ['decorated' => false]);

        $this->assertContains(file_get_contents(__DIR__.'/Fixtures/describe_env_vars.txt'), $tester->getDisplay(true));
    }

    public function provideIgnoreBackslashWhenFindingService()
    {
        return [
            [BackslashClass::class],
            ['FixturesBackslashClass'],
            ['\\'.BackslashClass::class],
        ];
    }
}
