<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Annotation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class GroupsTest extends TestCase
{
    public function testEmptyGroupsParameter()
    {
        self::expectException(InvalidArgumentException::class);
        new Groups([]);
    }

    /**
     * @group legacy
     */
    public function testEmptyGroupsParameterLegacy()
    {
        self::expectException(InvalidArgumentException::class);
        new Groups(['value' => []]);
    }

    /**
     * @group legacy
     */
    public function testNotAnArrayGroupsParameter()
    {
        self::expectException(InvalidArgumentException::class);
        new Groups(['value' => 12]);
    }

    public function testInvalidGroupsParameter()
    {
        self::expectException(InvalidArgumentException::class);
        new Groups(['a', 1, new \stdClass()]);
    }

    public function testGroupsParameters()
    {
        $validData = ['a', 'b'];

        $groups = new Groups($validData);
        self::assertEquals($validData, $groups->getGroups());
    }

    /**
     * @group legacy
     */
    public function testGroupsParametersLegacy()
    {
        $validData = ['a', 'b'];

        $groups = new Groups(['value' => $validData]);
        self::assertEquals($validData, $groups->getGroups());
    }

    public function testSingleGroup()
    {
        $groups = new Groups('a');
        self::assertEquals(['a'], $groups->getGroups());
    }

    /**
     * @group legacy
     */
    public function testSingleGroupLegacy()
    {
        $groups = new Groups(['value' => 'a']);
        self::assertEquals(['a'], $groups->getGroups());
    }
}
