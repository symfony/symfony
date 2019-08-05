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
class ContainerDebugCommandTest extends AbstractWebTestCase
{
    public function testDumpContainerIfNotExists()
    {
        static::bootKernel(['test_case' => 'ContainerDebug', 'root_config' => 'config.yml', 'debug' => true]);

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        @unlink(static::$kernel->getContainer()->getParameter('debug.container.dump'));

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'debug:container']);

        $this->assertFileExists(static::$kernel->getContainer()->getParameter('debug.container.dump'));
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
        $tester->run(['command' => 'debug:container', '--show-private' => true]);
        $this->assertStringContainsString('public', $tester->getDisplay());
        $this->assertStringContainsString('private_alias', $tester->getDisplay());

        $tester->run(['command' => 'debug:container']);
        $this->assertStringContainsString('public', $tester->getDisplay());
        $this->assertStringNotContainsString('private_alias', $tester->getDisplay());
    }
}
