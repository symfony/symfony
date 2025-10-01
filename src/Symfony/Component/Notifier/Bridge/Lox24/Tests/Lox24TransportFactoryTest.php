<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Lox24\Tests;

use Symfony\Component\Notifier\Bridge\Lox24\Lox24TransportFactory;
use Symfony\Component\Notifier\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Notifier\Test\IncompleteDsnTestTrait;
use Symfony\Component\Notifier\Test\MissingRequiredOptionTestTrait;
use Symfony\Component\Notifier\Transport\TransportFactoryInterface;

/**
 * @author Andrei Lebedev <andrew.lebedev@gmail.com>
 */
class Lox24TransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;
    use MissingRequiredOptionTestTrait;

    public static function supportsProvider(): iterable
    {
        yield [true, 'lox24://123456:aaaabbbbbbccccccdddddeeee@default?from=0611223344'];
        yield [false, 'somethingElse://accountSid:authToken@default?from=0611223344'];
    }

    public static function createProvider(): iterable
    {
        yield [
            'lox24://api.lox24.eu?from=0611223344',
            'lox24://USERID:TOKEN@default?from=0611223344',
        ];
        yield [
            'lox24://host.test?from=0611223344',
            'lox24://USERID:TOKEN@host.test?from=0611223344',
        ];
    }

    public static function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: from' => ['lox24://accountSid:authToken@default'];
    }

    public function createFactory(): TransportFactoryInterface
    {
        return new Lox24TransportFactory();
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield 'missing token' => ['invalid://default?from=0611223344'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing token' => ['lox24://default?from=0611223344'];
    }
}
