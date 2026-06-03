<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Security;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Bundle\SecurityBundle\Security\UserAuthenticator;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;
use Symfony\Component\Security\Http\Authenticator\HttpBasicAuthenticator;

class UserAuthenticatorTest extends TestCase
{
    public function testThrowsLogicExceptionIfCurrentRequestIsNull()
    {
        $container = new Container();
        $firewallMap = new FirewallMap($container, []);
        $requestStack = new RequestStack();
        $user = new InMemoryUser('username', 'password');
        $userProvider = new InMemoryUserProvider();
        $authenticator = new HttpBasicAuthenticator('name', $userProvider);
        $request = new Request();

        $userAuthenticator = new UserAuthenticator($firewallMap, $container, $requestStack);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot determine the correct Symfony\Bundle\SecurityBundle\Security\UserAuthenticator to use: there is no active Request and so, the firewall cannot be determined. Try using a specific Symfony\Bundle\SecurityBundle\Security\UserAuthenticator service.');

        $userAuthenticator->authenticateUser($user, $authenticator, $request);
    }
}
