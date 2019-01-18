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
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @group functional
 */
class RouterDebugCommandTest extends WebTestCase
{
    private $application;

    protected function setUp()
    {
        $kernel = static::createKernel(['test_case' => 'RouterDebug', 'root_config' => 'config.yml']);
        $this->application = new Application($kernel);
    }

    public function testDumpAllRoutes()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute([]);
        $display = $tester->getDisplay();

        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $this->assertContains('routerdebug_test', $display);
        $this->assertContains('/test', $display);
        $this->assertContains('/session', $display);
    }

    public function testDumpOneRoute()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(['name' => 'routerdebug_session_welcome']);

        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $this->assertContains('routerdebug_session_welcome', $tester->getDisplay());
        $this->assertContains('/session', $tester->getDisplay());
    }

    public function testSearchMultipleRoutes()
    {
        $tester = $this->createCommandTester();
        $tester->setInputs([3]);
        $ret = $tester->execute(['name' => 'routerdebug'], ['interactive' => true]);

        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $this->assertContains('Select one of the matching routes:', $tester->getDisplay());
        $this->assertContains('routerdebug_test', $tester->getDisplay());
        $this->assertContains('/test', $tester->getDisplay());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The route "gerard" does not exist.
     */
    public function testSearchWithThrow()
    {
        $tester = $this->createCommandTester();
        $tester->execute(['name' => 'gerard'], ['interactive' => true]);
    }

    public function testFilterRouteByMethod()
    {
        $tester = $this->createCommandTester();

        $ret = $tester->execute(['--method' => ['GET']]);
        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $this->assertContains('routerdebug_get', $tester->getDisplay());
        $this->assertContains('/test/get', $tester->getDisplay());
        $this->assertNotContains('routerdebug_post', $tester->getDisplay());
        $this->assertNotContains('/test/post', $tester->getDisplay());

        $ret = $tester->execute(['--method' => ['GET', 'POST']]);
        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $this->assertContains('routerdebug_get', $tester->getDisplay());
        $this->assertContains('/test/get', $tester->getDisplay());
        $this->assertContains('routerdebug_post', $tester->getDisplay());
        $this->assertContains('/test/post', $tester->getDisplay());
    }

    public function testFilterRouteByScheme()
    {
        $tester = $this->createCommandTester();

        $ret = $tester->execute(['--scheme' => 'http']);
        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $this->assertContains('routerdebug_get', $tester->getDisplay());
        $this->assertContains('/test/get', $tester->getDisplay());
        $this->assertNotContains('routerdebug_post', $tester->getDisplay());
        $this->assertNotContains('/test/post', $tester->getDisplay());

        $ret = $tester->execute(['--scheme' => 'https']);
        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $this->assertNotContains('routerdebug_get', $tester->getDisplay());
        $this->assertNotContains('/test/get', $tester->getDisplay());
        $this->assertContains('routerdebug_post', $tester->getDisplay());
        $this->assertContains('/test/post', $tester->getDisplay());
    }

    public function testFilterRouteByHost()
    {
        $tester = $this->createCommandTester();

        $ret = $tester->execute(['--match-host' => '^test\..*\.com']);
        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $this->assertNotContains('routerdebug_get', $tester->getDisplay());
        $this->assertNotContains('/test/get', $tester->getDisplay());
        $this->assertContains('routerdebug_post', $tester->getDisplay());
        $this->assertContains('/test/post', $tester->getDisplay());
        $this->assertContains('test.example.com', $tester->getDisplay());

        $ret = $tester->execute(['--match-host' => '.*\.example\.(com|org)']);
        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $this->assertNotContains('routerdebug_get', $tester->getDisplay());
        $this->assertNotContains('/test/get', $tester->getDisplay());
        $this->assertContains('routerdebug_post', $tester->getDisplay());
        $this->assertContains('/test/post', $tester->getDisplay());
        $this->assertContains('test.example.com', $tester->getDisplay());

        $ret = $tester->execute(['--match-host' => '.*\.example\.org']);
        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $this->assertNotContains('routerdebug_get', $tester->getDisplay());
        $this->assertNotContains('/test/get', $tester->getDisplay());
        $this->assertNotContains('routerdebug_post', $tester->getDisplay());
        $this->assertNotContains('/test/post', $tester->getDisplay());
        $this->assertNotContains('test.example.com', $tester->getDisplay());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage "*invalid_regex" does not seems to be a valid regex.
     */
    public function testFilterWithInvalidHostRegex()
    {
        $tester = $this->createCommandTester();

        $ret = $tester->execute(['--match-host' => '*invalid_regex']);
        $this->assertSame(1, $ret, 'Returns 0 in case of success');
    }

    public function testMultipleFilters()
    {
        $tester = $this->createCommandTester();

        $ret = $tester->execute(['--method' => ['GET', 'POST'], '--match-host' => '.*\.com']);
        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $this->assertNotContains('routerdebug_get', $tester->getDisplay());
        $this->assertNotContains('/test/get', $tester->getDisplay());
        $this->assertContains('routerdebug_post', $tester->getDisplay());
        $this->assertContains('/test/post', $tester->getDisplay());
    }

    private function createCommandTester(): CommandTester
    {
        $command = $this->application->get('debug:router');

        return new CommandTester($command);
    }
}
