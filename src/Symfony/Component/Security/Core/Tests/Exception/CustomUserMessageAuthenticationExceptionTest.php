<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\InMemoryUser;

class ChildCustomUserMessageAuthenticationException extends CustomUserMessageAuthenticationException
{
    public $childMember;

    public function __serialize(): array
    {
        return [$this->childMember, parent::__serialize()];
    }

    public function __unserialize(array $data): void
    {
        [$this->childMember, $parentData] = $data;

        parent::__unserialize($parentData);
    }
}

class CustomUserMessageAuthenticationExceptionTest extends TestCase
{
    public function testConstructWithSAfeMessage()
    {
        $e = new CustomUserMessageAuthenticationException('SAFE MESSAGE', ['foo' => true]);

        $this->assertEquals('SAFE MESSAGE', $e->getMessageKey());
        $this->assertEquals(['foo' => true], $e->getMessageData());
        $this->assertEquals('SAFE MESSAGE', $e->getMessage());
    }

    public function testSharedSerializedData()
    {
        $token = new UsernamePasswordToken(new InMemoryUser('foo', 'bar', ['ROLE_USER']), 'main', ['ROLE_USER']);

        $exception = new CustomUserMessageAuthenticationException();
        $exception->setToken($token);
        $exception->setSafeMessage('message', ['token' => $token]);

        $processed = unserialize(serialize($exception));
        $this->assertEquals($token, $processed->getToken());
        $this->assertEquals($token, $processed->getMessageData()['token']);
        $this->assertSame($processed->getToken(), $processed->getMessageData()['token']);
    }

    public function testSharedSerializedDataFromChild()
    {
        $token = new UsernamePasswordToken(new InMemoryUser('foo', 'bar', ['ROLE_USER']), 'main', ['ROLE_USER']);

        $exception = new ChildCustomUserMessageAuthenticationException();
        $exception->childMember = $token;
        $exception->setToken($token);

        $processed = unserialize(serialize($exception));
        $this->assertEquals($token, $processed->childMember);
        $this->assertEquals($token, $processed->getToken());
        $this->assertSame($processed->getToken(), $processed->childMember);
    }
}
