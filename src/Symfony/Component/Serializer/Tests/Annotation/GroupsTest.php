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
        $this->expectException(InvalidArgumentException::class);
        new Groups([]);
    }

    public function testInvalidGroupsParameter()
    {
        $this->expectException(InvalidArgumentException::class);
        new Groups(['a', 1, new \stdClass()]);
    }

    public function testGroupsParameters()
    {
        $validData = ['a', 'b'];

        $groups = new Groups($validData);
        $this->assertEquals($validData, $groups->getGroups());
    }

    public function testSingleGroup()
    {
        $groups = new Groups('a');
        $this->assertEquals(['a'], $groups->getGroups());
    }
}
