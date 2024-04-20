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
use Symfony\Bundle\FrameworkBundle\Command\SecretsListCommand;
use Symfony\Bundle\FrameworkBundle\Secrets\AbstractVault;
use Symfony\Bundle\FrameworkBundle\Secrets\DotenvVault;
use Symfony\Component\Console\Tester\CommandTester;

class SecretsListCommandTest extends TestCase
{
    /**
     * @backupGlobals enabled
     */
    public function testExecute()
    {
        $vault = $this->createMock(AbstractVault::class);
        $vault->method('list')->willReturn(['A' => 'a', 'B' => 'b', 'C' => null, 'D' => null, 'E' => null]);

        $_ENV = ['A' => '', 'B' => 'A', 'C' => '', 'D' => false, 'E' => null];
        $localVault = new DotenvVault('/not/a/path');

        $command = new SecretsListCommand($vault, $localVault);
        $tester = new CommandTester($command);
        $this->assertSame(0, $tester->execute([]));

        $expectedOutput = <<<EOTXT
             // Use "%%env(<name>)%%" to reference a secret in a config file.

             // To reveal the secrets run %s secrets:list --reveal

             -------- -------- -------------
              Secret   Value    Local Value
             -------- -------- -------------
              A        "a"
              B        "b"      ******
              C        ******
              D        ******   ******
              E        ******
             -------- -------- -------------

             // Local values override secret values.
             // Use secrets:set --local to define them.
            EOTXT;
        $this->assertStringMatchesFormat($expectedOutput, trim(preg_replace('/ ++$/m', '', $tester->getDisplay(true)), "\n"));
    }
}
