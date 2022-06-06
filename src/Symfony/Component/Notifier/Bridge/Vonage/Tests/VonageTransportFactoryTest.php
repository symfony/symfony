<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Vonage\Tests;

use Symfony\Component\Notifier\Bridge\Vonage\VonageTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;
use Symfony\Component\Notifier\Transport\TransportFactoryInterface;

final class VonageTransportFactoryTest extends TransportFactoryTestCase
{
    /**
     * @return VonageTransportFactory
     */
    public function createFactory(): TransportFactoryInterface
    {
        return new VonageTransportFactory();
    }

    public function createProvider(): iterable
    {
        yield [
            'vonage://host.test?from=0611223344',
            'vonage://apiKey:apiSecret@host.test?from=0611223344',
        ];
    }

    public function supportsProvider(): iterable
    {
        yield [true, 'vonage://apiKey:apiSecret@default?from=0611223344'];
        yield [false, 'somethingElse://apiKey:apiSecret@default?from=0611223344'];
    }

    public function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: from' => ['vonage://apiKey:apiSecret@default'];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://apiKey:apiSecret@default?from=0611223344'];
        yield ['somethingElse://apiKey:apiSecret@default']; // missing "from" option
    }
}
