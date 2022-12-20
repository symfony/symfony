<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Dotenv\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Dotenv\Command\DebugCommand;
use Symfony\Component\Dotenv\Dotenv;

class DebugCommandTest extends TestCase
{
    /**
     * @runInSeparateProcess
     */
    public function testErrorOnUninitializedDotenv()
    {
        $command = new DebugCommand('dev', __DIR__.'/Fixtures/Scenario1');
        $command->setHelperSet(new HelperSet([new FormatterHelper()]));
        $tester = new CommandTester($command);
        $tester->execute([]);
        $output = $tester->getDisplay();

        self::assertStringContainsString('[ERROR] Dotenv component is not initialized', $output);
    }

    public function testScenario1InDevEnv()
    {
        $output = $this->executeCommand(__DIR__.'/Fixtures/Scenario1', 'dev');

        // Scanned Files
        self::assertStringContainsString('⨯ .env.local.php', $output);
        self::assertStringContainsString('⨯ .env.dev.local', $output);
        self::assertStringContainsString('⨯ .env.dev', $output);
        self::assertStringContainsString('✓ .env.local', $output);
        self::assertStringContainsString('✓ .env'.\PHP_EOL, $output);

        // Skipped Files
        self::assertStringNotContainsString('.env.prod', $output);
        self::assertStringNotContainsString('.env.test', $output);
        self::assertStringNotContainsString('.env.dist', $output);

        // Variables
        self::assertStringContainsString('Variable   Value   .env.local   .env', $output);
        self::assertStringContainsString('FOO        baz     baz          bar', $output);
        self::assertStringContainsString('TEST123    true    n/a          true', $output);
    }

    public function testScenario1InTestEnv()
    {
        $output = $this->executeCommand(__DIR__.'/Fixtures/Scenario1', 'test');

        // Scanned Files
        self::assertStringContainsString('⨯ .env.local.php', $output);
        self::assertStringContainsString('⨯ .env.test.local', $output);
        self::assertStringContainsString('✓ .env.test', $output);
        self::assertStringContainsString('✓ .env'.\PHP_EOL, $output);

        // Skipped Files
        self::assertStringNotContainsString('.env.prod', $output);
        self::assertStringNotContainsString('.env.dev', $output);
        self::assertStringNotContainsString('.env.dist', $output);

        // Variables
        self::assertStringContainsString('Variable   Value   .env.test   .env', $output);
        self::assertStringContainsString('FOO        bar     n/a         bar', $output);
        self::assertStringContainsString('TEST123    false   false       true', $output);
    }

    public function testScenario1InProdEnv()
    {
        $output = $this->executeCommand(__DIR__.'/Fixtures/Scenario1', 'prod');

        // Scanned Files
        self::assertStringContainsString('⨯ .env.local.php', $output);
        self::assertStringContainsString('✓ .env.prod.local', $output);
        self::assertStringContainsString('⨯ .env.prod', $output);
        self::assertStringContainsString('✓ .env.local', $output);
        self::assertStringContainsString('✓ .env'.\PHP_EOL, $output);

        // Skipped Files
        self::assertStringNotContainsString('.env.dev', $output);
        self::assertStringNotContainsString('.env.test', $output);
        self::assertStringNotContainsString('.env.dist', $output);

        // Variables
        self::assertStringContainsString('Variable   Value   .env.prod.local   .env.local   .env', $output);
        self::assertStringContainsString('FOO        baz     n/a               baz          bar', $output);
        self::assertStringContainsString('HELLO      world   world             n/a          n/a', $output);
        self::assertStringContainsString('TEST123    true    n/a               n/a          true', $output);
    }

    public function testScenario2InProdEnv()
    {
        $output = $this->executeCommand(__DIR__.'/Fixtures/Scenario2', 'prod');

        // Scanned Files
        self::assertStringContainsString('✓ .env.local.php', $output);
        self::assertStringContainsString('⨯ .env.prod.local', $output);
        self::assertStringContainsString('✓ .env.prod', $output);
        self::assertStringContainsString('⨯ .env.local', $output);
        self::assertStringContainsString('✓ .env.dist', $output);

        // Skipped Files
        self::assertStringNotContainsString('.env'.\PHP_EOL, $output);
        self::assertStringNotContainsString('.env.dev', $output);
        self::assertStringNotContainsString('.env.test', $output);

        // Variables
        self::assertStringContainsString('Variable   Value   .env.local.php   .env.prod   .env.dist', $output);
        self::assertStringContainsString('FOO        BaR     BaR              BaR         n/a', $output);
        self::assertStringContainsString('TEST       1234    1234             1234        0000', $output);
    }

    public function testWarningOnEnvAndEnvDistFile()
    {
        $output = $this->executeCommand(__DIR__.'/Fixtures/Scenario3', 'dev');

        // Warning
        self::assertStringContainsString('[WARNING] The file .env.dist gets skipped', $output);
    }

    public function testWarningOnPhpEnvFile()
    {
        $output = $this->executeCommand(__DIR__.'/Fixtures/Scenario2', 'prod');

        // Warning
        self::assertStringContainsString('[WARNING] Due to existing dump file (.env.local.php)', $output);
    }

    private function executeCommand(string $projectDirectory, string $env): string
    {
        $_SERVER['TEST_ENV_KEY'] = $env;
        (new Dotenv('TEST_ENV_KEY'))->bootEnv($projectDirectory.'/.env');

        $command = new DebugCommand($env, $projectDirectory);
        $command->setHelperSet(new HelperSet([new FormatterHelper()]));
        $tester = new CommandTester($command);
        $tester->execute([]);

        return $tester->getDisplay();
    }
}
