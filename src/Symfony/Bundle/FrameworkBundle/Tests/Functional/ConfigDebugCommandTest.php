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

    /**
     * @return CommandTester
     */
    private function createCommandTester()
    {
        $command = $this->application->find('debug:config');

        return new CommandTester($command);
    }
}
