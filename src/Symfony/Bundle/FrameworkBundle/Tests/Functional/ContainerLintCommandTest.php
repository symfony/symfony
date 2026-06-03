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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('functional')]
class ContainerLintCommandTest extends AbstractWebTestCase
{
    private Application $application;

    #[DataProvider('containerLintProvider')]
    public function testLintContainer(string $configFile, bool $resolveEnvVars, int $expectedExitCode, string $expectedOutput)
    {
        $kernel = static::createKernel([
            'test_case' => 'ContainerLint',
            'root_config' => $configFile,
            'debug' => true,
        ]);
        $this->application = new Application($kernel);

        $tester = $this->createCommandTester();
        $exitCode = $tester->execute(['--resolve-env-vars' => $resolveEnvVars]);

        $this->assertSame($expectedExitCode, $exitCode);
        $this->assertStringContainsString($expectedOutput, $tester->getDisplay());
    }

    public static function containerLintProvider(): iterable
    {
        yield ['escaped_percent.yml', false, 0, 'The container was linted successfully'];
        yield ['escaped_percent.yml', true, 0, 'The container was linted successfully'];
        yield ['missing_env_var.yml', false, 0, 'The container was linted successfully'];
        yield ['missing_env_var.yml', true, 1, 'Environment variable not found: "BAR"'];
    }

    private function createCommandTester(): CommandTester
    {
        return new CommandTester($this->application->get('lint:container'));
    }
}
