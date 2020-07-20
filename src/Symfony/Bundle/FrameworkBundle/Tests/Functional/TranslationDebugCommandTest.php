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

use Symfony\Bundle\FrameworkBundle\Command\TranslationDebugCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @group functional
 */
class TranslationDebugCommandTest extends AbstractWebTestCase
{
    private $application;

    protected function setUp(): void
    {
        $kernel = static::createKernel(['test_case' => 'TransDebug', 'root_config' => 'config.yml']);
        $this->application = new Application($kernel);
    }

    public function testDumpAllTrans()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(['locale' => 'en']);

        $this->assertSame(
            TranslationDebugCommand::EXIT_CODE_MISSING | TranslationDebugCommand::EXIT_CODE_UNUSED,
            $ret,
            'Returns appropriate exit code in the event of error'
        );
        $this->assertStringContainsString('missing    messages     hello_from_construct_arg_service', $tester->getDisplay());
        $this->assertStringContainsString('missing    messages     hello_from_subscriber_service', $tester->getDisplay());
        $this->assertStringContainsString('missing    messages     hello_from_property_service', $tester->getDisplay());
        $this->assertStringContainsString('missing    messages     hello_from_method_calls_service', $tester->getDisplay());
        $this->assertStringContainsString('missing    messages     hello_from_controller', $tester->getDisplay());
        $this->assertStringContainsString('unused     validators   This value should be blank.', $tester->getDisplay());
        $this->assertStringContainsString('unused     security     Invalid CSRF token.', $tester->getDisplay());
    }

    private function createCommandTester(): CommandTester
    {
        $command = $this->application->find('debug:translation');

        return new CommandTester($command);
    }
}
