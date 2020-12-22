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
use Symfony\Component\Notifier\Tests\TransportFactoryTestCase;
use Symfony\Component\Notifier\Transport\TransportFactoryInterface;

final class OvhCloudTransportFactoryTest extends TransportFactoryTestCase
{
    /**
     * @return OvhCloudTransportFactory
     */
    public function createFactory(): TransportFactoryInterface
    {
        return new OvhCloudTransportFactory();
    }

    public function createProvider(): iterable
    {
        yield [
            'ovhcloud://host.test?consumer_key=consumerKey&service_name=serviceName',
            'ovhcloud://key:secret@host.test?consumer_key=consumerKey&service_name=serviceName',
        ];
    }

    public function supportsProvider(): iterable
    {
        yield [true, 'ovhcloud://key:secret@default?consumer_key=consumerKey&service_name=serviceName'];
        yield [false, 'somethingElse://key:secret@default?consumer_key=consumerKey&service_name=serviceName'];
    }

    public function incompleteDsnProvider(): iterable
    {
        yield 'missing option: consumer_key' => ['ovhcloud://key:secret@default?service_name=serviceName'];
        yield 'missing option: service_name' => ['ovhcloud://key:secret@default?consumer_key=consumerKey'];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://key:secret@default?consumer_key=consumerKey&service_name=serviceName'];
        yield ['somethingElse://key:secret@default?service_name=serviceName'];
        yield ['somethingElse://key:secret@default?consumer_key=consumerKey'];
    }
}
