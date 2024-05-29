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
use Symfony\Bridge\Monolog\Tests\RecordFactory;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * Tests the SwitchUserTokenProcessor.
 *
 * @author Igor Timoshenko <igor.timoshenko@i.ua>
 */
class SwitchUserTokenProcessorTest extends TestCase
{
    public function testProcessor()
    {
        $originalToken = new UsernamePasswordToken(new InMemoryUser('original_user', 'password', ['ROLE_SUPER_ADMIN']), 'provider', ['ROLE_SUPER_ADMIN']);
        $switchUserToken = new SwitchUserToken(new InMemoryUser('user', 'passsword', ['ROLE_USER']), 'provider', ['ROLE_USER'], $originalToken);
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($switchUserToken);

        $processor = new SwitchUserTokenProcessor($tokenStorage);
        $record = RecordFactory::create();
        $record = $processor($record);

        $expected = [
            'impersonator_token' => [
                'authenticated' => true,
                'roles' => ['ROLE_SUPER_ADMIN'],
                'user_identifier' => 'original_user',
            ],
        ];

        $this->assertEquals($expected, $record['extra']);
    }
}
