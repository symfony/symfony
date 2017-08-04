<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Tests\NamingStrategy;

use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\NamingStrategy\CamelCaseStrategy;

class CamelCaseStrategyTest extends TestCase
{
    /**
     * @var CamelCaseStrategy
     */
    private $namingStrategy;

    protected function setUp()
    {
        $this->namingStrategy = new CamelCaseStrategy();
    }

    public function getCamelCaseProperty()
    {
        return array(
            array(
                'test',
                array('getTest', 'test', 'isTest', 'hasTest'),
                array('setTest', 'test'),
                array(
                    array('addTest', 'removeTest'),
                ),
            ),
            array(
                'fooBar',
                array('getFooBar', 'fooBar', 'isFooBar', 'hasFooBar'),
                array('setFooBar', 'fooBar'),
                array(
                    array('addFooBar', 'removeFooBar'),
                ),
            ),
            array(
                'users',
                array('getUsers', 'users', 'isUsers', 'hasUsers'),
                array('setUsers', 'users'),
                array(
                    array('addUser', 'removeUser'),
                ),
            ),
            array(
                'userGroups',
                array('getUserGroups', 'userGroups', 'isUserGroups', 'hasUserGroups'),
                array('setUserGroups', 'userGroups'),
                array(
                    array('addUserGroup', 'removeUserGroup'),
                ),
            ),
            array(
                'circuses',
                array('getCircuses', 'circuses', 'isCircuses', 'hasCircuses'),
                array('setCircuses', 'circuses'),
                array(
                    array('addCircus', 'removeCircus'),
                    array('addCircuse', 'removeCircuse'),
                    array('addCircusis', 'removeCircusis'),
                ),
            ),
        );
    }

    public function getUnderscoreProperty()
    {
        return array(
            array(
                'foo_bar',
                array('getFooBar', 'fooBar', 'isFooBar', 'hasFooBar'),
                array('setFooBar', 'fooBar'),
                array(
                    array('addFooBar', 'removeFooBar'),
                ),
            ),
            array(
                'user_groups',
                array('getUserGroups', 'userGroups', 'isUserGroups', 'hasUserGroups'),
                array('setUserGroups', 'userGroups'),
                array(
                    array('addUserGroup', 'removeUserGroup'),
                ),
            ),
            array(
                'super_circuses',
                array('getSuperCircuses', 'superCircuses', 'isSuperCircuses', 'hasSuperCircuses'),
                array('setSuperCircuses', 'superCircuses'),
                array(
                    array('addSuperCircus', 'removeSuperCircus'),
                    array('addSuperCircuse', 'removeSuperCircuse'),
                    array('addSuperCircusis', 'removeSuperCircusis'),
                ),
            ),
        );
    }

    /**
     * @dataProvider getCamelCaseProperty
     */
    public function testCamelCaseProperty($property, $getters, $setters, $addersAndRemovers)
    {
        $this->assertSame($getters, $this->namingStrategy->getGetters('SomeClass', $property));
        $this->assertSame($setters, $this->namingStrategy->getSetters('SomeClass', $property));
        $this->assertSame($addersAndRemovers, $this->namingStrategy->getAddersAndRemovers('SomeClass', $property));
    }

    /**
     * @dataProvider getUnderscoreProperty
     */
    public function testUnderscoreProperty($property, $getters, $setters, $addersAndRemovers)
    {
        $this->assertSame($getters, $this->namingStrategy->getGetters('SomeClass', $property));
        $this->assertSame($setters, $this->namingStrategy->getSetters('SomeClass', $property));
        $this->assertSame($addersAndRemovers, $this->namingStrategy->getAddersAndRemovers('SomeClass', $property));
    }
}
