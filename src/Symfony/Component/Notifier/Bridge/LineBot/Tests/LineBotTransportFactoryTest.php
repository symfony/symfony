<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\LineBot\Tests;

use Symfony\Component\Notifier\Bridge\LineBot\LineBotTransportFactory;
use Symfony\Component\Notifier\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Notifier\Test\IncompleteDsnTestTrait;
use Symfony\Component\Notifier\Test\MissingRequiredOptionTestTrait;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Yi-Jyun Pan <me@pan93.com>
 */
final class LineBotTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;
    use MissingRequiredOptionTestTrait;

    private const MOCK_TOKEN = 'eyJhbGciOiJIUzI1NiJ9.eyJSb2xlIjoiQWRtaW4iL+CJJc3N1ZXIiOiJJc3N1ZXIiLCJVc2VybmFtZSI6IkphdmFJblVzZSIsImV4cCI6MTcyODU1MjA3OSwiaW+F0IjoxNzI4NTUyMDc5fQ.SPKpGKwsXBay2uXDh7tATW20S2vZpw9qcmYjNp46Ir/AB/12345677=';

    public function createFactory(): LineBotTransportFactory
    {
        return new LineBotTransportFactory();
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'linebot://host?receiver=abc&token=token'];
        yield [true, 'linebot://host'];
        yield [false, 'somethingElse://host'];
    }

    public static function createProvider(): iterable
    {
        $encodedToken = urlencode(self::MOCK_TOKEN);

        yield [
            'linebot://api.line.me?receiver=test',
            'linebot://'.$encodedToken.'@default?receiver=test',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield ['linebot://host.test?receiver=xxx', 'User is not set.'];
    }

    public static function missingRequiredOptionProvider(): iterable
    {
        yield ['linebot://token@host', 'receiver'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://token@host'];
        yield ['somethingElse://token@host?receiver=abc&token=token'];
    }

    public function testDsnToken()
    {
        $encodedToken = urlencode(self::MOCK_TOKEN);

        $uri = "linebot://$encodedToken@default?receiver=test";
        $dsn = new Dsn($uri);

        $this->assertSame(self::MOCK_TOKEN, $dsn->getUser());
    }
}
