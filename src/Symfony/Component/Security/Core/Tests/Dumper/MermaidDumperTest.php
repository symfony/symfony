<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Dumper;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Dumper\MermaidDirection;
use Symfony\Component\Security\Core\Dumper\MermaidDumper;
use Symfony\Component\Security\Core\Role\RoleHierarchy;

class MermaidDumperTest extends TestCase
{
    public function testDumpSimpleHierarchy()
    {
        $hierarchy = [
            'ROLE_ADMIN' => ['ROLE_USER'],
            'ROLE_SUPER_ADMIN' => ['ROLE_ADMIN', 'ROLE_ALLOWED_TO_SWITCH'],
        ];

        $roleHierarchy = new RoleHierarchy($hierarchy);
        $dumper = new MermaidDumper();
        $output = $dumper->dump($roleHierarchy);

        $this->assertStringContainsString('graph TB', $output);
        $this->assertStringContainsString('ROLE_ADMIN', $output);
        $this->assertStringContainsString('ROLE_USER', $output);
        $this->assertStringContainsString('ROLE_SUPER_ADMIN', $output);
        $this->assertStringContainsString('ROLE_ADMIN --> ROLE_USER', $output);
        $this->assertStringContainsString('ROLE_SUPER_ADMIN --> ROLE_ADMIN', $output);
        $this->assertStringContainsString('ROLE_SUPER_ADMIN --> ROLE_ALLOWED_TO_SWITCH', $output);
    }

    public function testDumpWithDirection()
    {
        $hierarchy = [
            'ROLE_ADMIN' => ['ROLE_USER'],
        ];

        $roleHierarchy = new RoleHierarchy($hierarchy);
        $dumper = new MermaidDumper();
        $output = $dumper->dump($roleHierarchy, MermaidDirection::LEFT_TO_RIGHT);

        $this->assertStringContainsString('graph LR', $output);
    }

    public function testDumpEmptyHierarchy()
    {
        $roleHierarchy = new RoleHierarchy([]);
        $dumper = new MermaidDumper();
        $output = $dumper->dump($roleHierarchy);

        $this->assertStringContainsString('graph TB', $output);
        $this->assertStringContainsString('classDef default fill:#e1f5fe;', $output);
    }

    public function testDumpComplexHierarchy()
    {
        $hierarchy = [
            'ROLE_SUPER_ADMIN' => ['ROLE_ADMIN', 'ROLE_ALLOWED_TO_SWITCH'],
            'ROLE_ADMIN' => ['ROLE_USER'],
            'ROLE_MANAGER' => ['ROLE_USER'],
            'ROLE_EDITOR' => ['ROLE_USER'],
        ];

        $roleHierarchy = new RoleHierarchy($hierarchy);
        $dumper = new MermaidDumper();
        $output = $dumper->dump($roleHierarchy);

        $this->assertStringContainsString('ROLE_SUPER_ADMIN', $output);
        $this->assertStringContainsString('ROLE_ADMIN', $output);
        $this->assertStringContainsString('ROLE_MANAGER', $output);
        $this->assertStringContainsString('ROLE_EDITOR', $output);
        $this->assertStringContainsString('ROLE_USER', $output);
        $this->assertStringContainsString('ROLE_ALLOWED_TO_SWITCH', $output);

        $this->assertStringContainsString('ROLE_SUPER_ADMIN --> ROLE_ADMIN', $output);
        $this->assertStringContainsString('ROLE_SUPER_ADMIN --> ROLE_ALLOWED_TO_SWITCH', $output);
        $this->assertStringContainsString('ROLE_ADMIN --> ROLE_USER', $output);
        $this->assertStringContainsString('ROLE_MANAGER --> ROLE_USER', $output);
        $this->assertStringContainsString('ROLE_EDITOR --> ROLE_USER', $output);
    }

    public function testInvalidDirection()
    {
        $this->expectException(\TypeError::class);

        $dumper = new MermaidDumper();
        $dumper->dump(new RoleHierarchy([]), 'INVALID');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('dataProviderValidDirection')]
    public function testValidDirections(MermaidDirection $direction)
    {
        $this->expectNotToPerformAssertions();
        $dumper = new MermaidDumper();
        $dumper->dump(new RoleHierarchy([]), $direction);
    }

    public static function dataProviderValidDirection()
    {
        return [
            [MermaidDirection::TOP_TO_BOTTOM],
            [MermaidDirection::TOP_DOWN],
            [MermaidDirection::BOTTOM_TO_TOP],
            [MermaidDirection::RIGHT_TO_LEFT],
            [MermaidDirection::LEFT_TO_RIGHT],
        ];
    }

    public function testRoleNameEscaping()
    {
        $hierarchy = [
            'ROLE_ADMIN-TEST' => ['ROLE_USER.SPECIAL'],
        ];

        $roleHierarchy = new RoleHierarchy($hierarchy);
        $dumper = new MermaidDumper();
        $output = $dumper->dump($roleHierarchy);

        $this->assertStringContainsString('ROLE_ADMIN_TEST', $output);
        $this->assertStringContainsString('ROLE_USER_SPECIAL', $output);
        $this->assertStringContainsString('ROLE_ADMIN_TEST --> ROLE_USER_SPECIAL', $output);
    }
}
