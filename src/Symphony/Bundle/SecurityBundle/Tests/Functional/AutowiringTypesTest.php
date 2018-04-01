<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\SecurityBundle\Tests\Functional;

use Symphony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symphony\Component\Security\Core\Authorization\TraceableAccessDecisionManager;

class AutowiringTypesTest extends WebTestCase
{
    public function testAccessDecisionManagerAutowiring()
    {
        static::bootKernel(array('debug' => false));
        $container = static::$kernel->getContainer();

        $autowiredServices = $container->get('test.autowiring_types.autowired_services');
        $this->assertInstanceOf(AccessDecisionManager::class, $autowiredServices->getAccessDecisionManager(), 'The security.access.decision_manager service should be injected in debug mode');

        static::bootKernel(array('debug' => true));
        $container = static::$kernel->getContainer();

        $autowiredServices = $container->get('test.autowiring_types.autowired_services');
        $this->assertInstanceOf(TraceableAccessDecisionManager::class, $autowiredServices->getAccessDecisionManager(), 'The debug.security.access.decision_manager service should be injected in non-debug mode');
    }

    protected static function createKernel(array $options = array())
    {
        return parent::createKernel(array('test_case' => 'AutowiringTypes') + $options);
    }
}
