<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Smsbox\Tests;

use Symfony\Component\Notifier\Bridge\Smsbox\SmsboxTransportFactory;
use Symfony\Component\Notifier\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Notifier\Test\IncompleteDsnTestTrait;
use Symfony\Component\Notifier\Test\MissingRequiredOptionTestTrait;

final class SmsboxTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;
    use MissingRequiredOptionTestTrait;

    public function createFactory(): SmsboxTransportFactory
    {
        return new SmsboxTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield ['smsbox://host.test?mode=Standard&strategy=4', 'smsbox://APIKEY@host.test?mode=Standard&strategy=4'];
        yield ['smsbox://host.test?mode=Expert&strategy=4&sender=SENDER', 'smsbox://APIKEY@host.test?mode=Expert&strategy=4&sender=SENDER'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield ['smsbox://APIKEY@host.test?strategy=4&sender=SENDER'];
        yield ['smsbox://APIKEY@host.test?mode=Standard&sender=SENDER'];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'smsbox://APIKEY@host.test?mode=MODE&strategy=STRATEGY&sender=SENDER'];
        yield [false, 'somethingElse://APIKEY@host.test?mode=MODE&strategy=STRATEGY&sender=SENDER'];
    }

    public static function missingRequiredOptionProvider(): iterable
    {
        yield ['smsbox://apiKey@host.test?strategy=4'];
        yield ['smsbox://apiKey@host.test?mode=Standard'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://APIKEY@host.test?mode=MODE&strategy=STRATEGY&sender=SENDER'];
        yield ['somethingElse://APIKEY@host.test'];
    }
}
