<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Command;

use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Command\SecretsListCommand;
use Symfony\Bundle\FrameworkBundle\Secrets\AbstractVault;
use Symfony\Bundle\FrameworkBundle\Secrets\DotenvVault;
use Symfony\Component\Console\Tester\CommandTester;

class SecretsListCommandTest extends TestCase
{
    #[BackupGlobals(true)]
    public function testExecute()
    {
        $vault = $this->createStub(AbstractVault::class);
        $vault->method('list')->willReturn(['A' => 'a', 'B' => 'b', 'C' => null, 'D' => null, 'E' => null]);

        $_ENV = ['A' => '', 'B' => 'A', 'C' => '', 'D' => false, 'E' => null];
        $localVault = new DotenvVault('/not/a/path');

        $command = new SecretsListCommand($vault, $localVault);
        $tester = new CommandTester($command);
        $this->assertSame(0, $tester->execute([]));

        $display = trim(preg_replace('/ ++$/m', '', $tester->getDisplay(true)), "\n");

        $this->assertStringContainsString('// Use "%env(<name>)%" to reference a secret in a config file.', $display);
        $this->assertMatchesRegularExpression('/\n\s*A\s+"a"\s*\n/', $display);
        $this->assertMatchesRegularExpression('/\n\s*B\s+"b"\s+\*\*\*\*\*\*\s*\n/', $display);
        $this->assertMatchesRegularExpression('/\n\s*C\s+\*\*\*\*\*\*\s*\n/', $display);
        $this->assertMatchesRegularExpression('/\n\s*D\s+\*\*\*\*\*\*\s+\*\*\*\*\*\*\s*\n/', $display);
        $this->assertMatchesRegularExpression('/\n\s*E\s+\*\*\*\*\*\*\s*\n/', $display);
        $this->assertStringContainsString('// Local values override secret values.', $display);
        $this->assertStringContainsString('// Use secrets:set --local to define them.', $display);
    }
}
