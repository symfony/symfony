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
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
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

    public function testParametersValuesAreResolved()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(['name' => 'framework']);

        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $this->assertStringContainsString("locale: '%env(LOCALE)%'", $tester->getDisplay());
        $this->assertStringContainsString('secret: test', $tester->getDisplay());
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

    private function createCommandTester(): CommandTester
    {
        $command = $this->application->find('debug:config');

        return new CommandTester($command);
    }
}
