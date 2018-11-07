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
class TranslationDebugCommandTest extends WebTestCase
{
    private $application;

    protected function setUp()
    {
        $kernel = static::createKernel(['test_case' => 'TransDebug', 'root_config' => 'config.yml']);
        $this->application = new Application($kernel);
    }

    public function testDumpAllTrans()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(['locale' => 'en']);

        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $this->assertContains('unused    validators   This value should be blank.', $tester->getDisplay());
        $this->assertContains('unused    security     Invalid CSRF token.', $tester->getDisplay());
    }

    private function createCommandTester(): CommandTester
    {
        $command = $this->application->find('debug:translation');

        return new CommandTester($command);
    }
}
