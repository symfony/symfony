<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\Tests\Functional;

use Symphony\Bundle\FrameworkBundle\Console\Application;
use Symphony\Component\Console\Input\ArrayInput;
use Symphony\Component\Console\Output\NullOutput;
use Symphony\Component\Console\Tester\CommandTester;

/**
 * @group functional
 */
class ConfigDebugCommandTest extends WebTestCase
{
    private $application;

    protected function setUp()
    {
        $kernel = static::createKernel(array('test_case' => 'ConfigDump', 'root_config' => 'config.yml'));
        $this->application = new Application($kernel);
        $this->application->doRun(new ArrayInput(array()), new NullOutput());
    }

    public function testDumpBundleName()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(array('name' => 'TestBundle'));

        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $this->assertContains('custom: foo', $tester->getDisplay());
    }

    public function testDumpBundleOption()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(array('name' => 'TestBundle', 'path' => 'custom'));

        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $this->assertContains('foo', $tester->getDisplay());
    }

    public function testParametersValuesAreResolved()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(array('name' => 'framework'));

        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $this->assertContains("locale: '%env(LOCALE)%'", $tester->getDisplay());
        $this->assertContains('secret: test', $tester->getDisplay());
    }

    public function testDumpUndefinedBundleOption()
    {
        $tester = $this->createCommandTester();
        $tester->execute(array('name' => 'TestBundle', 'path' => 'foo'));

        $this->assertContains('Unable to find configuration for "test.foo"', $tester->getDisplay());
    }

    /**
     * @return CommandTester
     */
    private function createCommandTester()
    {
        $command = $this->application->find('debug:config');

        return new CommandTester($command);
    }
}
