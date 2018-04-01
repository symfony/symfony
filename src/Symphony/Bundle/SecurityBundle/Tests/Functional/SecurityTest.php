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

use Symphony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symphony\Component\Security\Core\User\User;

class SecurityTest extends WebTestCase
{
    public function testServiceIsFunctional()
    {
        $kernel = self::createKernel(array('test_case' => 'SecurityHelper', 'root_config' => 'config.yml'));
        $kernel->boot();
        $container = $kernel->getContainer();

        // put a token into the storage so the final calls can function
        $user = new User('foo', 'pass');
        $token = new UsernamePasswordToken($user, '', 'provider', array('ROLE_USER'));
        $container->get('security.token_storage')->setToken($token);

        $security = $container->get('functional_test.security.helper');
        $this->assertTrue($security->isGranted('ROLE_USER'));
        $this->assertSame($token, $security->getToken());
    }
}
