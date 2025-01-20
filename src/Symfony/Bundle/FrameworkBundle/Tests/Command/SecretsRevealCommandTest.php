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

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Command\SecretsRevealCommand;
use Symfony\Bundle\FrameworkBundle\Secrets\AbstractVault;
use Symfony\Bundle\FrameworkBundle\Secrets\DotenvVault;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class SecretsRevealCommandTest extends TestCase
{
    public function testExecute()
    {
        $vault = $this->createMock(AbstractVault::class);
        $vault->method('list')->willReturn(['secretKey' => 'secretValue']);

        $command = new SecretsRevealCommand($vault);

        $tester = new CommandTester($command);
        $this->assertSame(Command::SUCCESS, $tester->execute(['name' => 'secretKey']));

        $this->assertEquals('secretValue', trim($tester->getDisplay(true)));
    }

    public function testInvalidName()
    {
        $vault = $this->createMock(AbstractVault::class);
        $vault->method('list')->willReturn(['secretKey' => 'secretValue']);

        $command = new SecretsRevealCommand($vault);

        $tester = new CommandTester($command);
        $this->assertSame(Command::INVALID, $tester->execute(['name' => 'undefinedKey']));

        $this->assertStringContainsString('The secret "undefinedKey" does not exist.', trim($tester->getDisplay(true)));
    }

    /**
     * @backupGlobals enabled
     */
    public function testLocalVaultOverride()
    {
        $vault = $this->createMock(AbstractVault::class);
        $vault->method('list')->willReturn(['secretKey' => 'secretValue']);

        $_ENV = ['secretKey' => 'newSecretValue'];
        $localVault = new DotenvVault('/not/a/path');

        $command = new SecretsRevealCommand($vault, $localVault);

        $tester = new CommandTester($command);
        $this->assertSame(Command::SUCCESS, $tester->execute(['name' => 'secretKey']));

        $this->assertEquals('newSecretValue', trim($tester->getDisplay(true)));
    }

    /**
     * @backupGlobals enabled
     */
    public function testOnlyLocalVaultContainsName()
    {
        $vault = $this->createMock(AbstractVault::class);
        $vault->method('list')->willReturn(['otherKey' => 'secretValue']);

        $_ENV = ['secretKey' => 'secretValue'];
        $localVault = new DotenvVault('/not/a/path');

        $command = new SecretsRevealCommand($vault, $localVault);

        $tester = new CommandTester($command);
        $this->assertSame(Command::SUCCESS, $tester->execute(['name' => 'secretKey']));

        $this->assertEquals('secretValue', trim($tester->getDisplay(true)));
    }
}
