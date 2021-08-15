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

        $this->assertStringContainsString('[ERROR] Dotenv component is not initialized', $output);
    }

    public function testScenario1InDevEnv()
    {
        $output = $this->executeCommand(__DIR__.'/Fixtures/Scenario1', 'dev');

        // Scanned Files
        $this->assertStringContainsString('⨯ .env.local.php', $output);
        $this->assertStringContainsString('⨯ .env.dev.local', $output);
        $this->assertStringContainsString('⨯ .env.dev', $output);
        $this->assertStringContainsString('✓ .env.local', $output);
        $this->assertStringContainsString('✓ .env'.\PHP_EOL, $output);

        // Skipped Files
        $this->assertStringNotContainsString('.env.prod', $output);
        $this->assertStringNotContainsString('.env.test', $output);
        $this->assertStringNotContainsString('.env.dist', $output);

        // Variables
        $this->assertStringContainsString('Variable   Value   .env.local   .env', $output);
        $this->assertStringContainsString('FOO        baz     baz          bar', $output);
        $this->assertStringContainsString('TEST123    true    n/a          true', $output);
    }

    public function testScenario1InTestEnv()
    {
        $output = $this->executeCommand(__DIR__.'/Fixtures/Scenario1', 'test');

        // Scanned Files
        $this->assertStringContainsString('⨯ .env.local.php', $output);
        $this->assertStringContainsString('⨯ .env.test.local', $output);
        $this->assertStringContainsString('✓ .env.test', $output);
        $this->assertStringContainsString('✓ .env'.\PHP_EOL, $output);

        // Skipped Files
        $this->assertStringNotContainsString('.env.prod', $output);
        $this->assertStringNotContainsString('.env.dev', $output);
        $this->assertStringNotContainsString('.env.dist', $output);

        // Variables
        $this->assertStringContainsString('Variable   Value   .env.test   .env', $output);
        $this->assertStringContainsString('FOO        bar     n/a         bar', $output);
        $this->assertStringContainsString('TEST123    false   false       true', $output);
    }

    public function testScenario1InProdEnv()
    {
        $output = $this->executeCommand(__DIR__.'/Fixtures/Scenario1', 'prod');

        // Scanned Files
        $this->assertStringContainsString('⨯ .env.local.php', $output);
        $this->assertStringContainsString('✓ .env.prod.local', $output);
        $this->assertStringContainsString('⨯ .env.prod', $output);
        $this->assertStringContainsString('✓ .env.local', $output);
        $this->assertStringContainsString('✓ .env'.\PHP_EOL, $output);

        // Skipped Files
        $this->assertStringNotContainsString('.env.dev', $output);
        $this->assertStringNotContainsString('.env.test', $output);
        $this->assertStringNotContainsString('.env.dist', $output);

        // Variables
        $this->assertStringContainsString('Variable   Value   .env.prod.local   .env.local   .env', $output);
        $this->assertStringContainsString('FOO        baz     n/a               baz          bar', $output);
        $this->assertStringContainsString('HELLO      world   world             n/a          n/a', $output);
        $this->assertStringContainsString('TEST123    true    n/a               n/a          true', $output);
    }

    public function testScenario2InProdEnv()
    {
        $output = $this->executeCommand(__DIR__.'/Fixtures/Scenario2', 'prod');

        // Scanned Files
        $this->assertStringContainsString('✓ .env.local.php', $output);
        $this->assertStringContainsString('⨯ .env.prod.local', $output);
        $this->assertStringContainsString('✓ .env.prod', $output);
        $this->assertStringContainsString('⨯ .env.local', $output);
        $this->assertStringContainsString('✓ .env.dist', $output);

        // Skipped Files
        $this->assertStringNotContainsString('.env'.\PHP_EOL, $output);
        $this->assertStringNotContainsString('.env.dev', $output);
        $this->assertStringNotContainsString('.env.test', $output);

        // Variables
        $this->assertStringContainsString('Variable   Value   .env.local.php   .env.prod   .env.dist', $output);
        $this->assertStringContainsString('FOO        BaR     BaR              BaR         n/a', $output);
        $this->assertStringContainsString('TEST       1234    1234             1234        0000', $output);
    }

    public function testWarningOnEnvAndEnvDistFile()
    {
        $output = $this->executeCommand(__DIR__.'/Fixtures/Scenario3', 'dev');

        // Warning
        $this->assertStringContainsString('[WARNING] The file .env.dist gets skipped', $output);
    }

    public function testWarningOnPhpEnvFile()
    {
        $output = $this->executeCommand(__DIR__.'/Fixtures/Scenario2', 'prod');

        // Warning
        $this->assertStringContainsString('[WARNING] Due to existing dump file (.env.local.php)', $output);
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
