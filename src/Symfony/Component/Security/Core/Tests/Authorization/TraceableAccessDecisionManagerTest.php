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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\DebugAccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\TraceableAccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

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

    /**
     * Test that the result of AccessDecisionManager::decide is not logged when called from a voter.
     */
    public function testDecideCalledByVoterNotLogged()
    {
        $token = $this->getMockBuilder(TokenInterface::class)->getMockForAbstractClass();

        $voter1 = $this
            ->getMockBuilder(VoterInterface::class)
            ->setMethods(array('vote'))
            ->getMock();

        $voter2 = $this
            ->getMockBuilder(VoterInterface::class)
            ->setMethods(array('vote'))
            ->getMock();

        $adm = new TraceableAccessDecisionManager(new AccessDecisionManager(array($voter1, $voter2)));

        $voter1
            ->expects($this->any())
            ->method('vote')
            ->willReturnCallback(function (TokenInterface $token, $subject, array $attributes) use ($adm) {
                if (\in_array('attr1', $attributes)) {
                    return $adm->decide($token, array('ROLE_USER')) && $subject instanceof \stdClass;
                }

                return VoterInterface::ACCESS_ABSTAIN;
            });

        $voter2
            ->expects($this->any())
            ->method('vote')
            ->willReturnCallback(function (TokenInterface $token, $subject, array $attributes) {
                if (\in_array('ROLE_USER', $attributes)) {
                    return VoterInterface::ACCESS_GRANTED;
                }

                return VoterInterface::ACCESS_ABSTAIN;
            });

        $adm->decide($token, array('attr1'));
        $adm->decide($token, array('attr1'), $obj = new \stdClass());
        $adm->decide($token, array('ROLE_USER'));
        $this->assertSame(array(
            array('attributes' => array('attr1'), 'object' => null, 'result' => false),
            array('attributes' => array('attr1'), 'object' => $obj, 'result' => true),
            array('attributes' => array('ROLE_USER'), 'object' => null, 'result' => true),
        ), $adm->getDecisionLog(), 'Wrong decision log returned');
    }
}
