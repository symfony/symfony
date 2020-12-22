<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Nexmo\Tests;

use Symfony\Component\Notifier\Bridge\Nexmo\NexmoTransportFactory;
use Symfony\Component\Notifier\Tests\TransportFactoryTestCase;
use Symfony\Component\Notifier\Transport\TransportFactoryInterface;

final class NexmoTransportFactoryTest extends TransportFactoryTestCase
{
    /**
     * @return NexmoTransportFactory
     */
    public function createFactory(): TransportFactoryInterface
    {
        return new NexmoTransportFactory();
    }

    public function createProvider(): iterable
    {
        yield [
            'nexmo://host.test?from=0611223344',
            'nexmo://apiKey:apiSecret@host.test?from=0611223344',
        ];
    }

    public function supportsProvider(): iterable
    {
        yield [true, 'nexmo://apiKey:apiSecret@default?from=0611223344'];
        yield [false, 'somethingElse://apiKey:apiSecret@default?from=0611223344'];
    }

    public function incompleteDsnProvider(): iterable
    {
        yield 'missing option: from' => ['nexmo://apiKey:apiSecret@default'];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://apiKey:apiSecret@default?from=0611223344'];
        yield ['somethingElse://apiKey:apiSecret@default']; // missing "from" option
    }
}
