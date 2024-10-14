<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Debug;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Debug\DebugRoleHierarchy;

class DebugRoleHierarchyTest extends TestCase
{
    public function testBuildHierarchy()
    {
        $hierarchy = [
            'ROLE_FOO' => ['ROLE_BAR'],
            'ROLE_FOO_BAR' => ['ROLE_BAZ'],
        ];

        $debugRoleHierarchy = new DebugRoleHierarchy($hierarchy);

        $this->assertNotEmpty($debugRoleHierarchy->getMap());
        $this->assertEquals([
            'ROLE_FOO' => [
                'ROLE_BAR' => [],
            ],
            'ROLE_FOO_BAR' => [
                'ROLE_BAZ' => [],
            ],
        ], $debugRoleHierarchy->getHierarchy());
    }

    public function testBuildHierarchyWithPlaceholders()
    {
        $debugRoleHierarchy = new DebugRoleHierarchy([
            'ROLE_FOOBAR' => ['ROLE_QUX'],
            'ROLE_FOO_*' => ['ROLE_FOOBAR'],
            'ROLE_BAR_*' => ['ROLE_BAR_FOO'],
            'ROLE_BAZ_*' => ['ROLE_FOO_BAR'],
        ]);

        foreach (['ROLE_FOO_*', 'ROLE_BAR_*', 'ROLE_BAZ_*'] as $placeholder) {
            $this->assertTrue($debugRoleHierarchy->isPlaceholder($placeholder));
        }
        $this->assertFalse($debugRoleHierarchy->isPlaceholder('ROLE_FOOBAR'));

        // Test full hierarchy tree
        $this->assertEquals([
            'ROLE_FOOBAR' => [
                'ROLE_QUX' => [],
            ],
            'ROLE_FOO_*' => [
                'ROLE_FOOBAR' => [
                    'ROLE_QUX' => [],
                ],
            ],
            'ROLE_BAR_*' => [
                'ROLE_BAR_FOO' => [],
            ],
            'ROLE_BAZ_*' => [
                'ROLE_FOO_BAR' => [
                    'ROLE_FOO_*' => [
                        'ROLE_FOOBAR' => [
                            'ROLE_QUX' => [],
                        ],
                    ],
                ],
            ],
        ], $debugRoleHierarchy->getHierarchy());

        // Test hierarchy tree for given roles
        $this->assertEquals([
            'ROLE_BAZ_A' => [
                'ROLE_BAZ_*' => [
                    'ROLE_FOO_BAR' => [
                        'ROLE_FOO_*' => [
                            'ROLE_FOOBAR' => [
                                'ROLE_QUX' => [],
                            ],
                        ],
                    ],
                ],
            ],
            'ROLE_FOO_A' => [
                'ROLE_FOO_*' => [
                    'ROLE_FOOBAR' => [
                        'ROLE_QUX' => [],
                    ],
                ],
            ],
        ], $debugRoleHierarchy->getHierarchy(['ROLE_BAZ_A', 'ROLE_FOO_A']));
    }
}
