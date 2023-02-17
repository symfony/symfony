<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Sinch\Tests;

use Symfony\Component\Notifier\Bridge\Sinch\SinchTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

final class SinchTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): SinchTransportFactory
    {
        return new SinchTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'sinch://host.test?from=0611223344',
            'sinch://accountSid:authToken@host.test?from=0611223344',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'sinch://accountSid:authToken@default?from=0611223344'];
        yield [false, 'somethingElse://accountSid:authToken@default?from=0611223344'];
    }

    public static function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: from' => ['sinch://accountSid:authToken@default'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://accountSid:authToken@default?from=0611223344'];
        yield ['somethingElse://accountSid:authToken@default']; // missing "from" option
    }
}
