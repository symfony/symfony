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
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * Tests the TokenProcessor.
 *
 * @author Dany Maillard <danymaillard93b@gmail.com>
 */
class TokenProcessorTest extends TestCase
{
    /**
     * @group legacy
     */
    public function testLegacyProcessor()
    {
        if (method_exists(UsernamePasswordToken::class, 'getUserIdentifier')) {
            self::markTestSkipped('This test requires symfony/security-core <5.3');
        }

        $token = new UsernamePasswordToken('user', 'password', 'provider', ['ROLE_USER']);
        $tokenStorage = self::createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $processor = new TokenProcessor($tokenStorage);
        $record = ['extra' => []];
        $record = $processor($record);

        self::assertArrayHasKey('token', $record['extra']);
        self::assertEquals($token->getUsername(), $record['extra']['token']['username']);
        self::assertEquals(['ROLE_USER'], $record['extra']['token']['roles']);
    }

    public function testProcessor()
    {
        if (!method_exists(UsernamePasswordToken::class, 'getUserIdentifier')) {
            self::markTestSkipped('This test requires symfony/security-core 5.3+');
        }

        $token = new UsernamePasswordToken(new InMemoryUser('user', 'password', ['ROLE_USER']), 'provider', ['ROLE_USER']);
        $tokenStorage = self::createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $processor = new TokenProcessor($tokenStorage);
        $record = ['extra' => []];
        $record = $processor($record);

        self::assertArrayHasKey('token', $record['extra']);
        self::assertEquals($token->getUserIdentifier(), $record['extra']['token']['user_identifier']);
        self::assertEquals(['ROLE_USER'], $record['extra']['token']['roles']);
    }
}
