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
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
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
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token);

        $processor = new TokenProcessor($tokenStorage);
        $record = RecordFactory::create();
        $record = $processor($record);

        $this->assertArrayHasKey('token', $record['extra']);
        $this->assertEquals($token->getUserIdentifier(), $record['extra']['token']['user_identifier']);
        $this->assertEquals(['ROLE_USER'], $record['extra']['token']['roles']);
    }

    public function testReentrantCallIsSkipped()
    {
        $innerRecord = RecordFactory::create();

        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $processor = new TokenProcessor($tokenStorage);

        $tokenStorage->method('getToken')
            ->willReturnCallback(static function () use ($processor, &$innerRecord) {
                // Simulate a re-entrant log call triggered during token resolution
                // (e.g. firewall initializer → EntityManager construction → deprecation warning)
                $innerRecord = $processor($innerRecord);

                return null;
            });

        $outerRecord = RecordFactory::create();
        $outerRecord = $processor($outerRecord);

        // Outer call completed and set the key (to null since getToken() returned null)
        $this->assertArrayHasKey('token', $outerRecord['extra']);
        $this->assertNull($outerRecord['extra']['token']);

        // Re-entrant call was silently dropped — record passed through unchanged
        $this->assertArrayNotHasKey('token', $innerRecord['extra']);
    }
}
