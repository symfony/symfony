<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Command\SecurityRoleHierarchyDumpCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Security\Core\Role\RoleHierarchy;

class SecurityRoleHierarchyDumpCommandTest extends TestCase
{
    public function testExecuteWithRoleHierarchy()
    {
        $hierarchy = [
            'ROLE_ADMIN' => ['ROLE_USER'],
            'ROLE_SUPER_ADMIN' => ['ROLE_ADMIN', 'ROLE_USER'],
        ];

        $roleHierarchy = new RoleHierarchy($hierarchy);
        $command = new SecurityRoleHierarchyDumpCommand($roleHierarchy);
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $expectedOutput = str_replace("\n", \PHP_EOL, <<<EXPECTED
            graph TB
                ROLE_ADMIN
                ROLE_USER
                ROLE_SUPER_ADMIN
                ROLE_ADMIN --> ROLE_USER
                ROLE_SUPER_ADMIN --> ROLE_ADMIN
                ROLE_SUPER_ADMIN --> ROLE_USER

            EXPECTED);

        $this->assertSame($expectedOutput, $output);
    }

    public function testExecuteWithCustomDirection()
    {
        $hierarchy = [
            'ROLE_ADMIN' => ['ROLE_USER'],
        ];

        $roleHierarchy = new RoleHierarchy($hierarchy);
        $command = new SecurityRoleHierarchyDumpCommand($roleHierarchy);
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute(['--direction' => 'BT']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('graph BT', $output);
    }

    public function testExecuteWithInvalidDirection()
    {
        $hierarchy = [
            'ROLE_ADMIN' => ['ROLE_USER'],
        ];

        $roleHierarchy = new RoleHierarchy($hierarchy);
        $command = new SecurityRoleHierarchyDumpCommand($roleHierarchy);
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute(['--direction' => 'INVALID']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Invalid direction', $commandTester->getDisplay());
    }
}
