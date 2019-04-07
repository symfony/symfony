<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Authentication;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Event\AuthenticationSensitiveEvent;

class AuthenticationSensitiveEventTest extends TestCase
{
    public static function provideTestAccessorMethodsData(): \Iterator
    {
        $createPassExtractorFunc = function ($default = null) {
            return function ($token, $event) use ($default): ?string {
                self::assertInstanceOf(TokenInterface::class, $token);
                self::assertInstanceOf(AuthenticationSensitiveEvent::class, $event);

                $c = $token->getCredentials();

                if (method_exists($c, 'getInnerCredentials')) {
                    return $c->getInnerCredentials();
                }

                if ($c instanceof \Closure) {
                    return ($c)();
                }

                if (isset($c[$k = sprintf('%s-key', $default)])) {
                    return $c[$k];
                }

                return $default;
            };
        };

        $createHasStrCastableClass = function (?string $return = null) {
            return new class($return) {
                private $return;

                public function __construct(?string $return = null)
                {
                    $this->return = $return;
                }

                public function __toString(): string
                {
                    return $this->return ?? '';
                }
            };
        };

        $createNotStrCastableClass = function (?string $return = null) {
            return new class($return) {
                private $return;

                public function __construct(?string $return = null)
                {
                    $this->return = $return;
                }

                public function getInnerCredentials(): string
                {
                    return $this->return ?? '';
                }
            };
        };

        $createCredentialsAFuncVal = function ($return = null) {
            return function () use ($return) {
                return $return;
            };
        };

        // expects credential password of "null" type
        yield [null];
        yield [null, $createHasStrCastableClass('')];
        yield [null, $createNotStrCastableClass('foo')];
        yield [null, ['unknown-index-foo' => 'foo']];
        yield [null, null, $createHasStrCastableClass('')];
        yield [null, null, $createNotStrCastableClass('foo')];
        yield [null, null, ['unknown-index-bar' => 'bar']];
        yield [null, null, null, $createPassExtractorFunc(null)];

        // expects credential password of "foo" value
        yield ['foo', 'foo'];
        yield ['foo', 'foo', 'bar'];
        yield ['foo', $createHasStrCastableClass('foo')];
        yield ['foo', $createNotStrCastableClass('foo'), null, $createPassExtractorFunc()];
        yield ['foo', $createCredentialsAFuncVal('foo'), null, $createPassExtractorFunc()];

        // expects credential password of "bar" value
        yield ['bar', null, 'bar'];
        yield ['bar', null, $createHasStrCastableClass('bar')];
        yield ['bar', null, $createNotStrCastableClass('bar'), $createPassExtractorFunc()];
        yield ['bar', null, $createCredentialsAFuncVal('bar'), $createPassExtractorFunc()];

        // expects credential password of "baz" value
        yield ['baz', null, null, $createPassExtractorFunc('baz')];

        // expects array value will be extracted for all supported indexes
        foreach (['password', 'api_key', 'api-key', 'secret'] as $index) {
            // expects credential password of "null" type
            yield [null, [$index => null]];
            yield [null, null, [$index => '']];
            yield [null, [$index => ''], [$index => null]];

            // expects credential password of "foo" value
            yield ['foo', [$index => 'foo']];
            yield ['foo', [$index => 'foo'], [$index => null]];
            yield ['foo', [$index => 'foo'], [$index => '']];
            yield ['foo', [$index => 'foo'], ['unknown-index-bar' => 'bar']];
            yield ['foo', [$index => 'foo'], [$index => 'bar']];

            // expects credential password of "bar" value
            yield ['bar', null, [$index => 'bar']];
            yield ['bar', [$index => null], [$index => 'bar']];
            yield ['bar', [$index => ''], [$index => 'bar']];
            yield ['bar', ['unknown-index-foo' => 'foo'], [$index => 'bar']];
            yield ['bar', [$index => $createNotStrCastableClass], [$index => 'bar']];

            // expects credential password of "{$index}-val" variable
            yield [
                sprintf('%s-val', $index),
                [sprintf('%s-key', $index) => sprintf('%s-val', $index)],
                null,
                $createPassExtractorFunc($index),
            ];
        }
    }

    /**
     * @dataProvider provideTestAccessorMethodsData
     *
     * @param string            $expectedPassword
     * @param string|array|null $finalCredentials
     * @param string|array|null $priorCredentials
     * @param \Closure|null     $passwordExtractor
     */
    public function testAccessorMethods(string $expectedPassword = null, $finalCredentials = null, $priorCredentials = null, \Closure $passwordExtractor = null): void
    {
        $event = new AuthenticationSensitiveEvent(
            $priorToken = $this->getTokenInterfaceMock($priorCredentials),
            $finalToken = $this->getTokenInterfaceMock($finalCredentials),
            AuthenticationProviderInterface::class
        );

        $this->assertSame($priorToken, $event->getPreAuthenticationToken());
        $this->assertSame($finalToken, $event->getAuthenticationToken());
        $this->assertSame(AuthenticationProviderInterface::class, $event->getAuthenticationProviderClassName());
        $this->assertSame($expectedPassword, $event->getAuthenticationTokenPassword($passwordExtractor));
    }

    private function getTokenInterfaceMock($credentials = null): TokenInterface
    {
        $token = $this
            ->getMockBuilder(TokenInterface::class)
            ->getMock();

        $token->expects($this->any())
            ->method('getCredentials')
            ->will($this->returnValue($credentials));

        return $token;
    }
}
