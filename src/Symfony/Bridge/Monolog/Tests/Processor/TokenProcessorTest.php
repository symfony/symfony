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
use Symfony\Bridge\Monolog\Tests\RecordFactory;
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
    public function testProcessor()
    {
        $token = new UsernamePasswordToken(new InMemoryUser('user', 'password', ['ROLE_USER']), 'provider', ['ROLE_USER']);
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $processor = new TokenProcessor($tokenStorage);
        $record = RecordFactory::create();
        $record = $processor($record);

        $this->assertArrayHasKey('token', $record['extra']);
        $this->assertEquals($token->getUserIdentifier(), $record['extra']['token']['user_identifier']);
        $this->assertEquals(['ROLE_USER'], $record['extra']['token']['roles']);
    }
}
