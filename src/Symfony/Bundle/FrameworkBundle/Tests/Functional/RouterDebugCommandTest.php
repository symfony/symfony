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
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @group functional
 */
class RouterDebugCommandTest extends AbstractWebTestCase
{
    private $application;

    protected function setUp(): void
    {
        $kernel = self::createKernel(['test_case' => 'RouterDebug', 'root_config' => 'config.yml']);
        $this->application = new Application($kernel);
    }

    public function testDumpAllRoutes()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute([]);
        $display = $tester->getDisplay();

        self::assertSame(0, $ret, 'Returns 0 in case of success');
        self::assertStringContainsString('routerdebug_test', $display);
        self::assertStringContainsString('/test', $display);
        self::assertStringContainsString('/session', $display);
    }

    public function testDumpOneRoute()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(['name' => 'routerdebug_session_welcome']);

        self::assertSame(0, $ret, 'Returns 0 in case of success');
        self::assertStringContainsString('routerdebug_session_welcome', $tester->getDisplay());
        self::assertStringContainsString('/session', $tester->getDisplay());
    }

    public function testSearchMultipleRoutes()
    {
        $tester = $this->createCommandTester();
        $tester->setInputs([3]);
        $ret = $tester->execute(['name' => 'routerdebug'], ['interactive' => true]);

        self::assertSame(0, $ret, 'Returns 0 in case of success');
        self::assertStringContainsString('Select one of the matching routes:', $tester->getDisplay());
        self::assertStringContainsString('routerdebug_test', $tester->getDisplay());
        self::assertStringContainsString('/test', $tester->getDisplay());
    }

    public function testSearchMultipleRoutesWithoutInteraction()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(['name' => 'routerdebug'], ['interactive' => false]);

        self::assertSame(0, $ret, 'Returns 0 in case of success');
        self::assertStringNotContainsString('Select one of the matching routes:', $tester->getDisplay());
        self::assertStringContainsString('routerdebug_session_welcome', $tester->getDisplay());
        self::assertStringContainsString('/session', $tester->getDisplay());
        self::assertStringContainsString('routerdebug_session_welcome_name', $tester->getDisplay());
        self::assertStringContainsString('/session/{name} ', $tester->getDisplay());
        self::assertStringContainsString('routerdebug_session_logout', $tester->getDisplay());
        self::assertStringContainsString('/session_logout', $tester->getDisplay());
        self::assertStringContainsString('routerdebug_test', $tester->getDisplay());
        self::assertStringContainsString('/test', $tester->getDisplay());
    }

    public function testSearchWithThrow()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('The route "gerard" does not exist.');
        $tester = $this->createCommandTester();
        $tester->execute(['name' => 'gerard'], ['interactive' => true]);
    }

    /**
     * @dataProvider provideCompletionSuggestions
     */
    public function testComplete(array $input, array $expectedSuggestions)
    {
        if (!class_exists(CommandCompletionTester::class)) {
            self::markTestSkipped('Test command completion requires symfony/console 5.4+.');
        }

        $tester = new CommandCompletionTester($this->application->get('debug:router'));
        self::assertSame($expectedSuggestions, $tester->complete($input));
    }

    public function provideCompletionSuggestions()
    {
        yield 'option --format' => [
            ['--format', ''],
            ['txt', 'xml', 'json', 'md'],
        ];

        yield 'route_name' => [
            [''],
            [
                'routerdebug_session_welcome',
                'routerdebug_session_welcome_name',
                'routerdebug_session_logout',
                'routerdebug_test',
            ],
        ];
    }

    private function createCommandTester(): CommandTester
    {
        $command = $this->application->get('debug:router');

        return new CommandTester($command);
    }
}
