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
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
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
        $this->assertStringContainsString($expected, str_replace(\PHP_EOL, "\n", $tester->getDisplay()));
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
        $this->assertStringContainsString($expected, str_replace(\PHP_EOL, "\n", $tester->getDisplay()));
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
        $this->assertStringContainsString($expected, str_replace(\PHP_EOL, "\n", $tester->getDisplay()));
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

    public function testOnlyAnEmptyAnswerStopsAskingForRoles()
    {
        $roleHierarchy = $this->createMock(RoleHierarchyInterface::class);
        $roleHierarchy
            ->expects($this->once())
            ->method('getReachableRoleNames')
            ->with(['ROLE_FOO', '0'])
            ->willReturnArgument(0);
        $tester = $this->createCommandTester($roleHierarchy);

        $tester->setInputs(['ROLE_FOO', '0', '']);
        $tester->execute([], ['interactive' => true]);

        $tester->assertCommandIsSuccessful();
        $this->assertSame(['ROLE_FOO', '0'], $tester->getInput()->getArgument('roles'));
    }

    public function testDebugCustomRoleHierarchyRequiresRoleArgument()
    {
        $roleHierarchy = $this->createStub(RoleHierarchyInterface::class);

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

    public function testDumpBuiltInRoleHierarchyAsMermaid()
    {
        $roleHierarchy = new DebugRoleHierarchy([
            'ROLE_ADMIN' => ['ROLE_USER'],
            'ROLE_SUPER_ADMIN' => ['ROLE_ADMIN'],
        ]);

        $tester = $this->createCommandTester($roleHierarchy);

        $tester->execute(['--format' => 'mermaid', '--direction' => 'LR']);

        $tester->assertCommandIsSuccessful();
        $expected = <<<EOF
            graph LR
                ROLE_ADMIN
                ROLE_USER
                ROLE_SUPER_ADMIN
                ROLE_ADMIN --> ROLE_USER
                ROLE_SUPER_ADMIN --> ROLE_ADMIN

            EOF;
        $this->assertSame($expected, str_replace(\PHP_EOL, "\n", $tester->getDisplay()));
    }

    public function testMermaidFormatDumpsTheWholeHierarchy()
    {
        $tester = $this->createCommandTester(new DebugRoleHierarchy(['ROLE_ADMIN' => ['ROLE_USER']]));

        $this->assertSame(1, $tester->execute(['roles' => ['ROLE_ADMIN'], '--format' => 'mermaid']));
        $this->assertStringContainsString('The "mermaid" format dumps the whole role hierarchy', $tester->getDisplay());
    }

    public function testDumpRoleHierarchySubclassAsMermaid()
    {
        $tester = $this->createCommandTester(new class(['ROLE_ADMIN' => ['ROLE_USER']]) extends RoleHierarchy {});

        $tester->execute(['--format' => 'mermaid'], ['interactive' => false]);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('ROLE_ADMIN --> ROLE_USER', $tester->getDisplay());
    }

    public function testRoleHierarchySubclassRequiresRoleArgumentWithTheTxtFormat()
    {
        $tester = $this->createCommandTester(new class(['ROLE_ADMIN' => ['ROLE_USER']]) extends RoleHierarchy {});

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Not enough arguments (missing: "roles").');

        $tester->execute([], ['interactive' => false]);
    }

    public function testMermaidFormatRequiresTheBuiltInRoleHierarchy()
    {
        $tester = $this->createCommandTester($this->createStub(RoleHierarchyInterface::class));

        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage(\sprintf('The "mermaid" format requires the role hierarchy to extend "%s".', RoleHierarchy::class));

        $tester->execute(['--format' => 'mermaid'], ['interactive' => false]);
    }

    public function testInvalidFormat()
    {
        $tester = $this->createCommandTester(new DebugRoleHierarchy([]));

        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('Supported formats are "txt", "mermaid".');

        $tester->execute(['--format' => 'json']);
    }

    private function createCommandTester(RoleHierarchyInterface $roleHierarchy): CommandTester
    {
        $application = new Application();
        $command = new DebugRolesCommand($roleHierarchy);
        $application->addCommand($command);

        return new CommandTester($command);
    }
}
