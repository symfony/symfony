<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Smsapi\Tests;

use Symfony\Component\Notifier\Bridge\Smsapi\SmsapiTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

final class SmsapiTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): SmsapiTransportFactory
    {
        return new SmsapiTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'smsapi://host.test',
            'smsapi://token@host.test',
        ];

        yield [
            'smsapi://host.test?from=testFrom',
            'smsapi://token@host.test?from=testFrom',
        ];

        yield [
            'smsapi://host.test?from=testFrom',
            'smsapi://token@host.test?from=testFrom&test=0',
        ];

        yield [
            'smsapi://host.test?from=testFrom',
            'smsapi://token@host.test?from=testFrom&fast=0',
        ];

        yield [
            'smsapi://host.test?from=testFrom',
            'smsapi://token@host.test?from=testFrom&test=false',
        ];

        yield [
            'smsapi://host.test?from=testFrom',
            'smsapi://token@host.test?from=testFrom&fast=false',
        ];

        yield [
            'smsapi://host.test?from=testFrom&test=1',
            'smsapi://token@host.test?from=testFrom&test=1',
        ];

        yield [
            'smsapi://host.test?from=testFrom&fast=1',
            'smsapi://token@host.test?from=testFrom&fast=1',
        ];

        yield [
            'smsapi://host.test?from=testFrom&test=1',
            'smsapi://token@host.test?from=testFrom&test=true',
        ];

        yield [
            'smsapi://host.test?from=testFrom&fast=1',
            'smsapi://token@host.test?from=testFrom&fast=true',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'smsapi://host'];
        yield [true, 'smsapi://host?fast=1'];
        yield [true, 'smsapi://host?test=1'];
        yield [true, 'smsapi://host?from=testFrom'];
        yield [true, 'smsapi://host?from=testFrom&fast=1'];
        yield [true, 'smsapi://host?from=testFrom&test=1'];
        yield [false, 'somethingElse://host?from=testFrom'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing token' => ['smsapi://host.test?from=testFrom'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://token@host?from=testFrom'];
    }
}
