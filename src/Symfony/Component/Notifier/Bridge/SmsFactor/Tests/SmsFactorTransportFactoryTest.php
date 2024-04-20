<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\SmsFactor\Tests;

use Symfony\Component\Notifier\Bridge\SmsFactor\SmsFactorTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

final class SmsFactorTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): SmsFactorTransportFactory
    {
        return new SmsFactorTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'sms-factor://api.smsfactor.com?sender=MyCompany&push_type=alert',
            'sms-factor://TOKEN@default?push_type=alert&sender=MyCompany',
        ];
        yield [
            'sms-factor://host.test?sender=MyCompany&push_type=marketing',
            'sms-factor://TOKEN@host.test?push_type=marketing&sender=MyCompany',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'sms-factor://TOKEN@default?sender=MyCompany&push_type=alert'];
        yield [true, 'sms-factor://TOKEN@default?sender=MyCompany&push_type=marketing'];
        yield [true, 'sms-factor://TOKEN@default?sender=MyCompany'];
        yield [true, 'sms-factor://TOKEN@default'];
        yield [true, 'sms-factor://TOKEN@api.example.com'];
        yield [false, 'somethingElse://TOKEN@default'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing token' => ['sms-factor://default?sender=MyCompany&push_type=marketing'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://TOKEN@default'];
    }
}
