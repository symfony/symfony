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
class ContainerDebugCommandTest extends WebTestCase
{
    public function testDumpContainerIfNotExists()
    {
        static::bootKernel(array('test_case' => 'ContainerDebug', 'root_config' => 'config.yml'));

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        @unlink(static::$kernel->getContainer()->getParameter('debug.container.dump'));

        $tester = new ApplicationTester($application);
        $tester->run(array('command' => 'debug:container'));

        $this->assertFileExists(static::$kernel->getContainer()->getParameter('debug.container.dump'));
    }

    public function testNoDebug()
    {
        static::bootKernel(array('test_case' => 'ContainerDebug', 'root_config' => 'config.yml', 'debug' => false));

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(array('command' => 'debug:container'));

        $this->assertContains('public', $tester->getDisplay());
    }

    public function testPrivateAlias()
    {
        static::bootKernel(array('test_case' => 'ContainerDebug', 'root_config' => 'config.yml'));

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(array('command' => 'debug:container', '--show-private' => true));
        $this->assertContains('public', $tester->getDisplay());
        $this->assertContains('private_alias', $tester->getDisplay());

        $tester->run(array('command' => 'debug:container'));
        $this->assertContains('public', $tester->getDisplay());
        $this->assertNotContains('private_alias', $tester->getDisplay());
    }
}
