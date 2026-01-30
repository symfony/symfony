<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Attribute;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class GroupsTest extends TestCase
{
    public function testEmptyGroupsParameter(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Groups([]);
    }

    public function testInvalidGroupsParameter(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Groups(['a', 1, new \stdClass()]);
    }

    public function testGroupsParameters(): void
    {
        $validData = ['a', 'b'];

        $groups = new Groups($validData);
        $this->assertEquals($validData, $groups->groups);
    }

    public function testSingleGroup(): void
    {
        $groups = new Groups('a');
        $this->assertEquals(['a'], $groups->groups);
    }
}
