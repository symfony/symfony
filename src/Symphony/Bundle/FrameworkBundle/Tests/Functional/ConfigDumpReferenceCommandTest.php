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
class ConfigDumpReferenceCommandTest extends WebTestCase
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
        $this->assertContains('test:', $tester->getDisplay());
        $this->assertContains('    custom:', $tester->getDisplay());
    }

    public function testDumpAtPath()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(array(
            'name' => 'test',
            'path' => 'array',
        ));

        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $this->assertSame(<<<'EOL'
# Default configuration for extension with alias: "test" at path "array"
array:
    child1:               ~
    child2:               ~


EOL
            , $tester->getDisplay(true));
    }

    public function testDumpAtPathXml()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(array(
            'name' => 'test',
            'path' => 'array',
            '--format' => 'xml',
        ));

        $this->assertSame(1, $ret);
        $this->assertContains('[ERROR] The "path" option is only available for the "yaml" format.', $tester->getDisplay());
    }

    /**
     * @return CommandTester
     */
    private function createCommandTester()
    {
        $command = $this->application->find('config:dump-reference');

        return new CommandTester($command);
    }
}
