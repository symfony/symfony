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

class TraceableAccessDecisionManagerTest extends TestCase
{
    /**
     * @dataProvider provideObjectsAndLogs
     */
    public function testDecideLog($expectedLog, $object)
    {
        $adm = new TraceableAccessDecisionManager(new AccessDecisionManager());
        $adm->decide($this->getMockBuilder(TokenInterface::class)->getMock(), ['ATTRIBUTE_1'], $object);

        $this->assertSame($expectedLog, $adm->getDecisionLog());
    }

    public function provideObjectsAndLogs()
    {
        $object = new \stdClass();

        yield [[['attributes' => ['ATTRIBUTE_1'], 'object' => null, 'result' => false]], null];
        yield [[['attributes' => ['ATTRIBUTE_1'], 'object' => true, 'result' => false]], true];
        yield [[['attributes' => ['ATTRIBUTE_1'], 'object' => 'jolie string', 'result' => false]], 'jolie string'];
        yield [[['attributes' => ['ATTRIBUTE_1'], 'object' => 12345, 'result' => false]], 12345];
        yield [[['attributes' => ['ATTRIBUTE_1'], 'object' => $x = fopen(__FILE__, 'r'), 'result' => false]], $x];
        yield [[['attributes' => ['ATTRIBUTE_1'], 'object' => $x = [], 'result' => false]], $x];
        yield [[['attributes' => ['ATTRIBUTE_1'], 'object' => $object, 'result' => false]], $object];
    }

    public function testDebugAccessDecisionManagerAliasExistsForBC()
    {
        $adm = new TraceableAccessDecisionManager(new AccessDecisionManager());

        $this->assertInstanceOf(DebugAccessDecisionManager::class, $adm, 'For BC, TraceableAccessDecisionManager must be an instance of DebugAccessDecisionManager');
    }
}
