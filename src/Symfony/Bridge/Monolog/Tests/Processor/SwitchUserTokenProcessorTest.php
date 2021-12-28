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
use Symfony\Bridge\Monolog\Processor\SwitchUserTokenProcessor;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\User;

/**
 * Tests the SwitchUserTokenProcessor.
 *
 * @author Igor Timoshenko <igor.timoshenko@i.ua>
 */
class SwitchUserTokenProcessorTest extends TestCase
{
    public function testProcessor()
    {
        if (class_exists(InMemoryUser::class)) {
            $originalToken = new UsernamePasswordToken(new InMemoryUser('original_user', 'password', ['ROLE_SUPER_ADMIN']), 'provider', ['ROLE_SUPER_ADMIN']);
            $switchUserToken = new SwitchUserToken(new InMemoryUser('user', 'passsword', ['ROLE_USER']), 'provider', ['ROLE_USER'], $originalToken);
        } else {
            $originalToken = new UsernamePasswordToken(new User('original_user', 'password', ['ROLE_SUPER_ADMIN']), null, 'provider', ['ROLE_SUPER_ADMIN']);
            $switchUserToken = new SwitchUserToken(new User('user', 'passsword', ['ROLE_USER']), null, 'provider', ['ROLE_USER'], $originalToken);
        }
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($switchUserToken);

        $processor = new SwitchUserTokenProcessor($tokenStorage);
        $record = ['extra' => []];
        $record = $processor($record);

        $expected = [
            'impersonator_token' => [
                'authenticated' => true,
                'roles' => ['ROLE_SUPER_ADMIN'],
                'username' => 'original_user',
            ],
        ];
        if (method_exists($originalToken, 'getUserIdentifier')) {
            $expected['impersonator_token']['user_identifier'] = 'original_user';
        }

        $this->assertEquals($expected, $record['extra']);
    }
}
