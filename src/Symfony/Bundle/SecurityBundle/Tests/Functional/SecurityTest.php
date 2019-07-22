<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional;

use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\User;

class SecurityTest extends WebTestCase
{
    public function testServiceIsFunctional()
    {
        $kernel = self::createKernel(['test_case' => 'SecurityHelper', 'root_config' => 'config.yml']);
        $kernel->boot();
        $container = $kernel->getContainer();

        // put a token into the storage so the final calls can function
        $user = new User('foo', 'pass');
        $token = new UsernamePasswordToken($user, '', 'provider', ['ROLE_USER']);
        $container->get('security.token_storage')->setToken($token);

        $security = $container->get('functional_test.security.helper');
        $this->assertTrue($security->isGranted('ROLE_USER'));
        $this->assertSame($token, $security->getToken());
    }
}
