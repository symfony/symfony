<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Esendex\Tests;

use Symfony\Component\Notifier\Bridge\Esendex\OrangeSmsTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;
use Symfony\Component\Notifier\Transport\TransportFactoryInterface;

final class OrangeSmsTransportFactoryTest extends TransportFactoryTestCase
{
    /**
     * @return OrangeSmsTransportFactory
     */
    public function createFactory(): TransportFactoryInterface
    {
        return new OrangeSmsTransportFactory();
    }

    public function createProvider(): iterable
    {
        yield [
            'orangesms://default?from=FROM&sender_name=SENDER_NAME',
            'orangesms://CLIENT_ID:CLIENT_SECRET@default?from=FROM&sender_name=SENDER_NAME',
        ];
    }

    public function supportsProvider(): iterable
    {
        yield [true, 'orangesms://CLIENT_ID:CLIENT_SECRET@default?from=FROM&sender_name=SENDER_NAME'];
        yield [false, 'somethingElse://CLIENT_ID:CLIENT_SECRET@default'];
    }

    public function incompleteDsnProvider(): iterable
    {
        yield 'missing credentials' => ['orangesms://default?from=FROM&sender_name=SENDER_NAME'];
        yield 'missing Client ID' => ['orangesms://:CLIENT_SECRET@default?from=FROM&sender_name=SENDER_NAME'];
        yield 'missing client Secret' => ['orangesms://CLIENT_ID:@default?from=FROM&sender_name=SENDER_NAME'];
    }

    public function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: from' => ['orangesms://CLIENT_ID:CLIENT_SECRET@default?sender_name=SENDER_NAME'];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://CLIENT_ID:CLIENT_SECRET@default?from=FROM&sender_name=SENDER_NAME'];
        yield ['somethingElse://CLIENT_ID:CLIENT_SECRET@host?sender_name=SENDER_NAME']; // missing "from" option
    }
}
