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
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

class ChildCustomUserMessageAuthenticationException extends CustomUserMessageAuthenticationException
{
    public function serialize()
    {
        return serialize([$this->childMember, parent::serialize()]);
    }

    public function unserialize($str)
    {
        list($this->childMember, $parentData) = unserialize($str);

        parent::unserialize($parentData);
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
        $token = new AnonymousToken('foo', 'bar');

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
        $token = new AnonymousToken('foo', 'bar');

        $exception = new ChildCustomUserMessageAuthenticationException();
        $exception->childMember = $token;
        $exception->setToken($token);

        $processed = unserialize(serialize($exception));
        $this->assertEquals($token, $processed->childMember);
        $this->assertEquals($token, $processed->getToken());
        $this->assertSame($processed->getToken(), $processed->childMember);
    }
}
