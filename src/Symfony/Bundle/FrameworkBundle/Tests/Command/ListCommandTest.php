<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\Command\ListCommand;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Tests\Functional\AbstractWebTestCase;
use Symfony\Component\Console\Tester\ApplicationTester;

/**
 * @group functional
 */
class ListCommandTest extends AbstractWebTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        static::deleteTmpDir();
    }

    public function testNotDisplaysHyperlinkIfNoDebugFileLinkFormatter()
    {
        static::bootKernel(['test_case' => 'ListCommand', 'root_config' => 'config_with_no_framework_ide.yml']);

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'list']);

        $this->assertStringContainsString('list Lists commands', preg_replace('/ +/', ' ', $tester->getDisplay()));
    }

    public function testDisplaysHyperlinkIfDebugFileLinkFormatter()
    {
        static::bootKernel(['test_case' => 'ListCommand', 'root_config' => 'config_with_framework_ide.yml']);

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'list']);

        $this->assertStringContainsString('list ^ Lists commands', preg_replace('/ +/', ' ', $tester->getDisplay()));
    }

    public function testDisplaysHyperlinkOnlyOnce()
    {
        static::bootKernel(['test_case' => 'ListCommand', 'root_config' => 'config_with_framework_ide.yml']);

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'list']);
        $tester->run(['command' => 'list']);

        $this->assertStringContainsString('list ^ Lists commands', preg_replace('/ +/', ' ', $tester->getDisplay()));
        $this->assertStringNotContainsString('list ^^ Lists commands', preg_replace('/ +/', ' ', $tester->getDisplay()));
    }
}
