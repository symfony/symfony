<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\SmsSluzba\Tests;

use Symfony\Component\Notifier\Bridge\SmsSluzba\SmsSluzbaTransportFactory;
use Symfony\Component\Notifier\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Notifier\Test\IncompleteDsnTestTrait;

final class SmsSluzbaTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;

    public function createFactory(): SmsSluzbaTransportFactory
    {
        return new SmsSluzbaTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'sms-sluzba://host.test',
            'sms-sluzba://username:password@host.test',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing username and password' => ['sms-sluzba://host'];
        yield 'missing password' => ['sms-sluzba://username@host'];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'sms-sluzba://username:password@default'];
        yield [false, 'somethingElse://username:password@default'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://username:password@default'];
    }
}
