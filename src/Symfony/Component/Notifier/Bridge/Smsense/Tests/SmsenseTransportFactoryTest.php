<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Smsense\Tests;

use Symfony\Component\Notifier\Bridge\Smsense\SmsenseTransportFactory;
use Symfony\Component\Notifier\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Notifier\Test\IncompleteDsnTestTrait;

class SmsenseTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;

    public function createFactory(): SmsenseTransportFactory
    {
        return new SmsenseTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'smsense://host.test?from=Symfony',
            'smsense://api_token@host.test?from=Symfony',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'smsense://api_key@default'];
        yield [false, 'somethingElse://api_key@default'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://api_key@default'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield ['smsense://default'];
    }
}
