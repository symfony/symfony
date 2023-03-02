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
        $kernel = static::createKernel(['test_case' => 'RouterDebug', 'root_config' => 'config.yml']);
        $this->application = new Application($kernel);
    }

    public function testDumpAllRoutes()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute([]);
        $display = $tester->getDisplay();

        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $this->assertStringContainsString('routerdebug_test', $display);
        $this->assertStringContainsString('/test', $display);
        $this->assertStringContainsString('/session', $display);
    }

    public function testDumpOneRoute()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(['name' => 'routerdebug_session_welcome']);

        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $this->assertStringContainsString('routerdebug_session_welcome', $tester->getDisplay());
        $this->assertStringContainsString('/session', $tester->getDisplay());
    }

    public function testSearchMultipleRoutes()
    {
        $tester = $this->createCommandTester();
        $tester->setInputs([3]);
        $ret = $tester->execute(['name' => 'routerdebug'], ['interactive' => true]);

        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $this->assertStringContainsString('Select one of the matching routes:', $tester->getDisplay());
        $this->assertStringContainsString('routerdebug_test', $tester->getDisplay());
        $this->assertStringContainsString('/test', $tester->getDisplay());
    }

    public function testSearchMultipleRoutesWithoutInteraction()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(['name' => 'routerdebug'], ['interactive' => false]);

        $this->assertSame(0, $ret, 'Returns 0 in case of success');
        $this->assertStringNotContainsString('Select one of the matching routes:', $tester->getDisplay());
        $this->assertStringContainsString('routerdebug_session_welcome', $tester->getDisplay());
        $this->assertStringContainsString('/session', $tester->getDisplay());
        $this->assertStringContainsString('routerdebug_session_welcome_name', $tester->getDisplay());
        $this->assertStringContainsString('/session/{name} ', $tester->getDisplay());
        $this->assertStringContainsString('routerdebug_session_logout', $tester->getDisplay());
        $this->assertStringContainsString('/session_logout', $tester->getDisplay());
        $this->assertStringContainsString('routerdebug_test', $tester->getDisplay());
        $this->assertStringContainsString('/test', $tester->getDisplay());
    }

    public function testSearchWithThrow()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The route "gerard" does not exist.');
        $tester = $this->createCommandTester();
        $tester->execute(['name' => 'gerard'], ['interactive' => true]);
    }

    /**
     * @dataProvider provideCompletionSuggestions
     */
    public function testComplete(array $input, array $expectedSuggestions)
    {
        $tester = new CommandCompletionTester($this->application->get('debug:router'));
        $this->assertSame($expectedSuggestions, $tester->complete($input));
    }

    public static function provideCompletionSuggestions()
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
