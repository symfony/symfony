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
        yield array(null);
        yield array(null, $createHasStrCastableClass(''));
        yield array(null, $createNotStrCastableClass('foo'));
        yield array(null, array('unknown-index-foo' => 'foo'));
        yield array(null, null, $createHasStrCastableClass(''));
        yield array(null, null, $createNotStrCastableClass('foo'));
        yield array(null, null, array('unknown-index-bar' => 'bar'));
        yield array(null, null, null, $createPassExtractorFunc(null));

        // expects credential password of "foo" value
        yield array('foo', 'foo');
        yield array('foo', 'foo', 'bar');
        yield array('foo', $createHasStrCastableClass('foo'));
        yield array('foo', $createNotStrCastableClass('foo'), null, $createPassExtractorFunc());
        yield array('foo', $createCredentialsAFuncVal('foo'), null, $createPassExtractorFunc());

        // expects credential password of "bar" value
        yield array('bar', null, 'bar');
        yield array('bar', null, $createHasStrCastableClass('bar'));
        yield array('bar', null, $createNotStrCastableClass('bar'), $createPassExtractorFunc());
        yield array('bar', null, $createCredentialsAFuncVal('bar'), $createPassExtractorFunc());

        // expects credential password of "baz" value
        yield array('baz', null, null, $createPassExtractorFunc('baz'));

        // expects array value will be extracted for all supported indexes
        foreach (array('password', 'api_key', 'api-key', 'secret') as $index) {
            // expects credential password of "null" type
            yield array(null, array($index => null));
            yield array(null, null, array($index => ''));
            yield array(null, array($index => ''), array($index => null));

            // expects credential password of "foo" value
            yield array('foo', array($index => 'foo'));
            yield array('foo', array($index => 'foo'), array($index => null));
            yield array('foo', array($index => 'foo'), array($index => ''));
            yield array('foo', array($index => 'foo'), array('unknown-index-bar' => 'bar'));
            yield array('foo', array($index => 'foo'), array($index => 'bar'));

            // expects credential password of "bar" value
            yield array('bar', null, array($index => 'bar'));
            yield array('bar', array($index => null), array($index => 'bar'));
            yield array('bar', array($index => ''), array($index => 'bar'));
            yield array('bar', array('unknown-index-foo' => 'foo'), array($index => 'bar'));
            yield array('bar', array($index => $createNotStrCastableClass), array($index => 'bar'));

            // expects credential password of "{$index}-val" variable
            yield array(
                sprintf('%s-val', $index),
                array(sprintf('%s-key', $index) => sprintf('%s-val', $index)),
                null,
                $createPassExtractorFunc($index),
            );
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
