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

        $this->assertEmpty($output);
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

    public function testEscapedRoleNamesKeepTheirOriginalNameAsLabel()
    {
        $roleHierarchy = new RoleHierarchy([
            'ROLE_ADMIN-TEST' => ['ROLE_USER'],
            'ROLE_*' => ['ROLE_USER.SPECIAL'],
            'ROLE_A#quot;]-->EVIL[x' => ['ROLE_USER'],
        ]);

        $output = (new MermaidDumper())->dump($roleHierarchy);

        $this->assertSame(<<<'MERMAID'
            graph TB
                ROLE_ADMIN_TEST["ROLE_ADMIN-TEST"]
                ROLE_USER
                ROLE__["ROLE_*"]
                ROLE_USER_SPECIAL["ROLE_USER.SPECIAL"]
                ROLE_A_quot_____EVIL_x["ROLE_A#35;quot;]-->EVIL[x"]
                ROLE_ADMIN_TEST --> ROLE_USER
                ROLE__ --> ROLE_USER_SPECIAL
                ROLE_A_quot_____EVIL_x --> ROLE_USER
            MERMAID, $output);
    }

    public function testRolesWithTheSameNormalizedNameGetDistinctNodes()
    {
        $roleHierarchy = new RoleHierarchy([
            'ROLE_A-B' => ['ROLE_A_B'],
            'ROLE_A.B' => ['ROLE_A_B_1'],
            'ROLE_АДМИН' => ['ROLE_ГОСТЬ'],
        ]);

        $output = (new MermaidDumper())->dump($roleHierarchy);

        $this->assertSame(<<<'MERMAID'
            graph TB
                ROLE_A_B_2["ROLE_A-B"]
                ROLE_A_B
                ROLE_A_B_3["ROLE_A.B"]
                ROLE_A_B_1
                ROLE___________["ROLE_АДМИН"]
                ROLE____________1["ROLE_ГОСТЬ"]
                ROLE_A_B_2 --> ROLE_A_B
                ROLE_A_B_3 --> ROLE_A_B_1
                ROLE___________ --> ROLE____________1
            MERMAID, $output);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('provideRolesWithTheSameNormalizedNameInAnyOrder')]
    public function testNodeIdsDoNotDependOnTheOrderOfTheRoles(array $hierarchy)
    {
        $output = (new MermaidDumper())->dump(new RoleHierarchy($hierarchy));

        $this->assertStringContainsString("\n    ROLE_A_B\n", $output);
        $this->assertStringContainsString("\n    ROLE_A_B_1[\"ROLE_A-B\"]\n", $output);
        $this->assertStringContainsString("\n    ROLE_A_B_2[\"ROLE_A.B\"]\n", $output);
    }

    public static function provideRolesWithTheSameNormalizedNameInAnyOrder(): iterable
    {
        yield [['ROLE_A-B' => ['ROLE_USER'], 'ROLE_A.B' => ['ROLE_USER'], 'ROLE_A_B' => ['ROLE_USER']]];
        yield [['ROLE_A_B' => ['ROLE_USER'], 'ROLE_A.B' => ['ROLE_USER'], 'ROLE_A-B' => ['ROLE_USER']]];
        yield [['ROLE_A.B' => ['ROLE_USER'], 'ROLE_A_B' => ['ROLE_USER'], 'ROLE_A-B' => ['ROLE_USER']]];
    }

    public function testReservedWordsAreNotUsedAsNodeIds()
    {
        $roleHierarchy = new RoleHierarchy([
            'ROLE_ADMIN' => ['end', 'END', 'class'],
            'constructor' => ['end'],
        ]);

        $output = (new MermaidDumper())->dump($roleHierarchy);

        $this->assertSame(<<<'MERMAID'
            graph TB
                ROLE_ADMIN
                end_1["end"]
                END
                class_1["class"]
                constructor_1["constructor"]
                ROLE_ADMIN --> end_1
                ROLE_ADMIN --> END
                ROLE_ADMIN --> class_1
                constructor_1 --> end_1
            MERMAID, $output);
    }
}
