<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\OvhCloud\Tests;

use Symfony\Component\Notifier\Bridge\OvhCloud\OvhCloudTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

final class OvhCloudTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): OvhCloudTransportFactory
    {
        return new OvhCloudTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'ovhcloud://host.test?service_name=serviceName',
            'ovhcloud://key:secret@host.test?consumer_key=consumerKey&service_name=serviceName',
        ];

        yield [
            'ovhcloud://host.test?service_name=serviceName&sender=sender',
            'ovhcloud://key:secret@host.test?consumer_key=consumerKey&service_name=serviceName&sender=sender',
        ];

        yield [
            'ovhcloud://host.test?service_name=serviceName',
            'ovhcloud://key:secret@host.test?consumer_key=consumerKey&service_name=serviceName&no_stop_clause=0',
        ];

        yield [
            'ovhcloud://host.test?service_name=serviceName',
            'ovhcloud://key:secret@host.test?consumer_key=consumerKey&service_name=serviceName&no_stop_clause=1',
        ];

        yield [
            'ovhcloud://host.test?service_name=serviceName',
            'ovhcloud://key:secret@host.test?consumer_key=consumerKey&service_name=serviceName&no_stop_clause=true',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'ovhcloud://key:secret@default?consumer_key=consumerKey&service_name=serviceName&sender=sender'];
        yield [true, 'ovhcloud://key:secret@default?consumer_key=consumerKey&service_name=serviceName&no_stop_clause=1'];
        yield [false, 'somethingElse://key:secret@default?consumer_key=consumerKey&service_name=serviceName&sender=sender'];
    }

    public static function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: consumer_key' => ['ovhcloud://key:secret@default?service_name=serviceName'];
        yield 'missing option: service_name' => ['ovhcloud://key:secret@default?consumer_key=consumerKey'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://key:secret@default?consumer_key=consumerKey&service_name=serviceName&sender=sender'];
        yield ['somethingElse://key:secret@default?service_name=serviceName'];
        yield ['somethingElse://key:secret@default?consumer_key=consumerKey'];
        yield ['somethingElse://key:secret@default?sender=sender'];
    }
}
