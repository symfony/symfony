<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Authorization;

use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\DebugAccessDecisionManager;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class DebugAccessDecisionManagerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provideObjectsAndLogs
     */
    public function testDecideLog($expectedLog, $object)
    {
        $adm = new DebugAccessDecisionManager(new AccessDecisionManager());
        $adm->decide($this->getMock(TokenInterface::class), array('ATTRIBUTE_1'), $object);

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
}
