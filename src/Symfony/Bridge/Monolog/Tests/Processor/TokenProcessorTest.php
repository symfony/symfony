<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Tests\Processor;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Monolog\Processor\TokenProcessor;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Tests the TokenProcessor.
 *
 * @author Dany Maillard <danymaillard93b@gmail.com>
 */
class TokenProcessorTest extends TestCase
{
    public function testProcessor()
    {
        $token = new UsernamePasswordToken('user', 'password', 'provider', ['ROLE_USER']);
        $tokenStorage = $this->getMockBuilder(TokenStorageInterface::class)->getMock();
        $tokenStorage->method('getToken')->willReturn($token);

        $processor = new TokenProcessor($tokenStorage);
        $record = ['extra' => []];
        $record = $processor($record);

        $this->assertArrayHasKey('token', $record['extra']);
        $this->assertEquals($token->getUsername(), $record['extra']['token']['username']);
        $this->assertEquals($token->isAuthenticated(), $record['extra']['token']['authenticated']);
        $roles = array_map(function ($role) { return $role->getRole(); }, $token->getRoles());
        $this->assertEquals($roles, $record['extra']['token']['roles']);
    }
}
