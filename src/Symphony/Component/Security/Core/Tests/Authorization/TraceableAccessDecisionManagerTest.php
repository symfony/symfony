<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Core\Tests\Authorization;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symphony\Component\Security\Core\Authorization\DebugAccessDecisionManager;
use Symphony\Component\Security\Core\Authorization\TraceableAccessDecisionManager;
use Symphony\Component\Security\Core\Authentication\Token\TokenInterface;

class TraceableAccessDecisionManagerTest extends TestCase
{
    /**
     * @dataProvider provideObjectsAndLogs
     */
    public function testDecideLog($expectedLog, $object)
    {
        $adm = new TraceableAccessDecisionManager(new AccessDecisionManager());
        $adm->decide($this->getMockBuilder(TokenInterface::class)->getMock(), array('ATTRIBUTE_1'), $object);

        $this->assertSame($expectedLog, $adm->getDecisionLog());
    }

    public function provideObjectsAndLogs()
    {
        $object = new \stdClass();

        yield array(array(array('attributes' => array('ATTRIBUTE_1'), 'object' => null, 'result' => false)), null);
        yield array(array(array('attributes' => array('ATTRIBUTE_1'), 'object' => true, 'result' => false)), true);
        yield array(array(array('attributes' => array('ATTRIBUTE_1'), 'object' => 'jolie string', 'result' => false)), 'jolie string');
        yield array(array(array('attributes' => array('ATTRIBUTE_1'), 'object' => 12345, 'result' => false)), 12345);
        yield array(array(array('attributes' => array('ATTRIBUTE_1'), 'object' => $x = fopen(__FILE__, 'r'), 'result' => false)), $x);
        yield array(array(array('attributes' => array('ATTRIBUTE_1'), 'object' => $x = array(), 'result' => false)), $x);
        yield array(array(array('attributes' => array('ATTRIBUTE_1'), 'object' => $object, 'result' => false)), $object);
    }

    public function testDebugAccessDecisionManagerAliasExistsForBC()
    {
        $adm = new TraceableAccessDecisionManager(new AccessDecisionManager());

        $this->assertInstanceOf(DebugAccessDecisionManager::class, $adm, 'For BC, TraceableAccessDecisionManager must be an instance of DebugAccessDecisionManager');
    }
}
