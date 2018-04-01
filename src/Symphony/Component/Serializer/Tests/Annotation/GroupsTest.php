<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Serializer\Tests\Annotation;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Serializer\Annotation\Groups;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class GroupsTest extends TestCase
{
    /**
     * @expectedException \Symphony\Component\Serializer\Exception\InvalidArgumentException
     */
    public function testEmptyGroupsParameter()
    {
        new Groups(array('value' => array()));
    }

    /**
     * @expectedException \Symphony\Component\Serializer\Exception\InvalidArgumentException
     */
    public function testNotAnArrayGroupsParameter()
    {
        new Groups(array('value' => 12));
    }

    /**
     * @expectedException \Symphony\Component\Serializer\Exception\InvalidArgumentException
     */
    public function testInvalidGroupsParameter()
    {
        new Groups(array('value' => array('a', 1, new \stdClass())));
    }

    public function testGroupsParameters()
    {
        $validData = array('a', 'b');

        $groups = new Groups(array('value' => $validData));
        $this->assertEquals($validData, $groups->getGroups());
    }

    public function testSingleGroup()
    {
        $groups = new Groups(array('value' => 'a'));
        $this->assertEquals(array('a'), $groups->getGroups());
    }
}
