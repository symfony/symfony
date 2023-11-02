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
use Symfony\Bundle\SecurityBundle\Command\DebugRolesCommand;
use Symfony\Bundle\SecurityBundle\Debug\DebugRoleHierarchy;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

class DebugRolesCommandTest extends TestCase
{
    public function testDebugBuiltInRoleHierarchy()
    {
        $roleHierarchy = new DebugRoleHierarchy([
            'ROLE_FOO' => ['ROLE_BAR'],
            'ROLE_BAR' => ['ROLE_BAZ'],
        ]);

        $tester = $this->createCommandTester($roleHierarchy);

        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $expected = <<<EOF
        ROLE_FOO:
          - ROLE_BAR
          - ROLE_BAZ

        ROLE_BAR:
          - ROLE_BAZ

        EOF;
        $this->assertStringContainsString($expected, $tester->getDisplay());
    }

    public function testDebugBuiltInHierarchyWithTreeOption()
    {
        $roleHierarchy = new DebugRoleHierarchy([
            'ROLE_FOO' => ['ROLE_BAR'],
            'ROLE_BAR' => ['ROLE_BAZ'],
        ]);

        $tester = $this->createCommandTester($roleHierarchy);

        $tester->execute(['--tree' => true]);

        $tester->assertCommandIsSuccessful();
        $expected = <<<EOF
        ROLE_FOO
        └── ROLE_BAR
            └── ROLE_BAZ

        ROLE_BAR
        └── ROLE_BAZ

        EOF;
        $this->assertStringContainsString($expected, $tester->getDisplay());
    }

    public function testDebugCustomRoleHierarchy()
    {
        $roleHierarchy = $this->createMock(RoleHierarchyInterface::class);
        $roleHierarchy
            ->expects($this->once())
            ->method('getReachableRoleNames')
            ->with(['ROLE_FOO'])
            ->willReturn([
                'ROLE_FOO',
                'ROLE_BAR',
            ]);
        $tester = $this->createCommandTester($roleHierarchy);

        $tester->execute(['roles' => ['ROLE_FOO']]);

        $tester->assertCommandIsSuccessful();
        $expected = <<<EOF
         * ROLE_FOO
         * ROLE_BAR
        EOF;
        $this->assertStringContainsString($expected, $tester->getDisplay());
    }

    public function testDebugCustomRoleHierarchyWithNoArgumentsAsksInteractively()
    {
        $roleHierarchy = $this->createMock(RoleHierarchyInterface::class);
        $roleHierarchy
            ->expects($this->once())
            ->method('getReachableRoleNames')
            ->with(['ROLE_FOO'])
            ->willReturnArgument(0);
        $tester = $this->createCommandTester($roleHierarchy);

        $tester->setInputs(['ROLE_FOO', '']);
        $tester->execute([], ['interactive' => true]);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('Enter a role to debug', $tester->getDisplay());
        $this->assertEquals(['ROLE_FOO'], $tester->getInput()->getArgument('roles'));
    }

    public function testDebugCustomRoleHierarchyRequiresRoleArgument()
    {
        $roleHierarchy = $this->createMock(RoleHierarchyInterface::class);

        $tester = $this->createCommandTester($roleHierarchy);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Not enough arguments (missing: "roles").');

        $tester->execute([], ['interactive' => false]);
    }

    public function testDebugCustomRoleHierarchyIgnoresTreeOption()
    {
        $roleHierarchy = $this->createMock(RoleHierarchyInterface::class);
        $roleHierarchy
            ->expects($this->once())
            ->method('getReachableRoleNames')
            ->with(['ROLE_FOO'])
            ->willReturnArgument(0);

        $tester = $this->createCommandTester($roleHierarchy);

        $tester->execute(['roles' => ['ROLE_FOO'], '--tree' => true]);

        $tester->assertCommandIsSuccessful();
        $this->assertNull($tester->getInput()->getOption('tree'));
        $this->assertStringContainsString('Ignoring option "--tree"', $tester->getDisplay());
    }

    private function createCommandTester(RoleHierarchyInterface $roleHierarchy): CommandTester
    {
        $application = new Application();
        $command = new DebugRolesCommand($roleHierarchy);
        $application->add($command);

        return new CommandTester($command);
    }
}
