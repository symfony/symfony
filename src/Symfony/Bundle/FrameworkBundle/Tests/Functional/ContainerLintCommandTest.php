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
class ContainerLintCommandTest extends AbstractWebTestCase
{
    private Application $application;

    /**
     * @dataProvider containerLintProvider
     */
    public function testLintContainer(string $configFile, string $expectedOutput)
    {
        $kernel = static::createKernel([
            'test_case' => 'ContainerDebug',
            'root_config' => $configFile,
            'debug' => true,
        ]);
        $this->application = new Application($kernel);

        $tester = $this->createCommandTester();
        $exitCode = $tester->execute([]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString($expectedOutput, $tester->getDisplay());
    }

    public function testLintContainerChecksServicesOnlyReferencedByTaggedIterators()
    {
        $kernel = static::createKernel([
            'test_case' => 'ContainerLint',
            'debug' => true,
        ]);
        $this->application = new Application($kernel);

        $tester = $this->createCommandTester();
        $exitCode = $tester->execute([]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Invalid definition for service "tagged_item"', $tester->getDisplay());
    }

    public static function containerLintProvider(): array
    {
        return [
            'default container' => ['config.yml', 'The container was linted successfully'],
            'missing dump file' => ['no_dump.yml', 'The container was linted successfully'],
        ];
    }

    private function createCommandTester(): CommandTester
    {
        return new CommandTester($this->application->get('lint:container'));
    }
}
