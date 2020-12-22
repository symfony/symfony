<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Twilio\Tests;

use Symfony\Component\Notifier\Bridge\Twilio\TwilioTransportFactory;
use Symfony\Component\Notifier\Tests\TransportFactoryTestCase;
use Symfony\Component\Notifier\Transport\TransportFactoryInterface;

final class TwilioTransportFactoryTest extends TransportFactoryTestCase
{
    /**
     * @return TwilioTransportFactory
     */
    public function createFactory(): TransportFactoryInterface
    {
        return new TwilioTransportFactory();
    }

    public function createProvider(): iterable
    {
        yield [
            'twilio://host.test?from=0611223344',
            'twilio://accountSid:authToken@host.test?from=0611223344',
        ];
    }

    public function supportsProvider(): iterable
    {
        yield [true, 'twilio://accountSid:authToken@default?from=0611223344'];
        yield [false, 'somethingElse://accountSid:authToken@default?from=0611223344'];
    }

    public function incompleteDsnProvider(): iterable
    {
        yield 'missing option: from' => ['twilio://accountSid:authToken@default'];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://accountSid:authToken@default?from=0611223344'];
        yield ['somethingElse://accountSid:authToken@default']; // missing "from" option
    }
}
