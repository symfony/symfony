<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

class UnserializeTrampolineGadget
{
    public static bool $fired = false;

    public function __toString(): string
    {
        self::$fired = true;

        return '';
    }
}

/**
 * A serialized object whose __unserialize() restores a typed string property
 * must reject \Stringable values, otherwise a forged payload turns that
 * property assignment into a __toString trampoline firing a gadget.
 */
class UnserializeTrampolineTest extends TestCase
{
    #[DataProvider('provideStringSlots')]
    public function testUnserializeRejectsStringableTrampoline(string $class, int $size, int $slot)
    {
        $data = array_fill(0, $size, null);
        $data[$slot] = new UnserializeTrampolineGadget();

        $payload = \sprintf('O:%d:"%s":%d:{', \strlen($class), $class, \count($data));
        foreach ($data as $key => $value) {
            $payload .= serialize($key).serialize($value);
        }
        $payload .= '}';

        UnserializeTrampolineGadget::$fired = false;

        try {
            unserialize($payload);
            $this->fail('Expected BadMethodCallException.');
        } catch (\BadMethodCallException $e) {
        }

        $this->assertFalse(UnserializeTrampolineGadget::$fired, '__toString gadget must not fire during unserialize');
    }

    public static function provideStringSlots(): iterable
    {
        yield 'UsernamePasswordToken::$firewallName' => [UsernamePasswordToken::class, 3, 1];
        yield 'PreAuthenticatedToken::$firewallName' => [PreAuthenticatedToken::class, 3, 1];
        yield 'RememberMeToken::$firewallName' => [RememberMeToken::class, 3, 1];
        yield 'SwitchUserToken::$originatedFromUri' => [SwitchUserToken::class, 3, 1];
        yield 'AuthenticationException::$message' => [AuthenticationException::class, 5, 2];
        yield 'AuthenticationException::$file' => [AuthenticationException::class, 5, 3];
        yield 'UserNotFoundException::$identifier' => [UserNotFoundException::class, 2, 0];
        yield 'CustomUserMessageAuthenticationException::$messageKey' => [CustomUserMessageAuthenticationException::class, 3, 1];
        yield 'CustomUserMessageAccountStatusException::$messageKey' => [CustomUserMessageAccountStatusException::class, 3, 1];
    }
}
