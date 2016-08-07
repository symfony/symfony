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

        yield array(array(array('attributes' => array('ATTRIBUTE_1'), 'object' => 'NULL', 'result' => false)), null);
        yield array(array(array('attributes' => array('ATTRIBUTE_1'), 'object' => 'boolean (true)', 'result' => false)), true);
        yield array(array(array('attributes' => array('ATTRIBUTE_1'), 'object' => 'string (jolie string)', 'result' => false)), 'jolie string');
        yield array(array(array('attributes' => array('ATTRIBUTE_1'), 'object' => 'integer (12345)', 'result' => false)), 12345);
        yield array(array(array('attributes' => array('ATTRIBUTE_1'), 'object' => 'resource', 'result' => false)), fopen(__FILE__, 'r'));
        yield array(array(array('attributes' => array('ATTRIBUTE_1'), 'object' => 'array', 'result' => false)), array());
        yield array(array(array('attributes' => array('ATTRIBUTE_1'), 'object' => sprintf('stdClass (object hash: %s)', spl_object_hash($object)), 'result' => false)), $object);
    }
}
