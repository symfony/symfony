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
use Symfony\Component\Notifier\Transport\TransportFactoryInterface;

final class SmsapiTransportFactoryTest extends TransportFactoryTestCase
{
    /**
     * @return SmsapiTransportFactory
     */
    public function createFactory(): TransportFactoryInterface
    {
        return new SmsapiTransportFactory();
    }

    public function createProvider(): iterable
    {
        yield [
            'smsapi://host.test?from=testFrom',
            'smsapi://token@host.test?from=testFrom',
        ];
    }

    public function supportsProvider(): iterable
    {
        yield [true, 'smsapi://host?from=testFrom'];
        yield [false, 'somethingElse://host?from=testFrom'];
    }

    public function incompleteDsnProvider(): iterable
    {
        yield 'missing token' => ['smsapi://host.test?from=testFrom'];
    }

    public function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: from' => ['smsapi://token@host'];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://token@host?from=testFrom'];
        yield ['somethingElse://token@host']; // missing "from" option
    }
}
