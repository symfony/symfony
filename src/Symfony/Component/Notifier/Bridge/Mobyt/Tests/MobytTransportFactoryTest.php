<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Mobyt\Tests;

use Symfony\Component\Notifier\Bridge\Mobyt\MobytTransportFactory;
use Symfony\Component\Notifier\Tests\TransportFactoryTestCase;
use Symfony\Component\Notifier\Transport\TransportFactoryInterface;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class MobytTransportFactoryTest extends TransportFactoryTestCase
{
    /**
     * @return MobytTransportFactory
     */
    public function createFactory(): TransportFactoryInterface
    {
        return new MobytTransportFactory();
    }

    public function createProvider(): iterable
    {
        yield [
            'mobyt://host.test?from=FROM&type_quality=LL',
            'mobyt://accountSid:authToken@host.test?from=FROM',
        ];

        yield [
            'mobyt://host.test?from=FROM&type_quality=N',
            'mobyt://accountSid:authToken@host.test?from=FROM&type_quality=N',
        ];
    }

    public function supportsProvider(): iterable
    {
        yield [true, 'mobyt://accountSid:authToken@host.test?from=FROM'];
        yield [false, 'somethingElse://accountSid:authToken@host.test?from=FROM'];
    }

    public function incompleteDsnProvider(): iterable
    {
        yield 'missing token' => ['mobyt://host.test?from=FROM'];
        yield 'missing option: from' => ['mobyt://accountSid:authToken@host'];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://accountSid:authToken@host.test?from=FROM'];
        yield ['somethingElse://accountSid:authToken@host.test']; // missing "from" option
    }
}
