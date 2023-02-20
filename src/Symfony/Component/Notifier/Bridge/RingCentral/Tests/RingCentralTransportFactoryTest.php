<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\RingCentral\Tests;

use Symfony\Component\Notifier\Bridge\RingCentral\RingCentralTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

final class RingCentralTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): RingCentralTransportFactory
    {
        return new RingCentralTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield ['ringcentral://host.test?from=0611223344', 'ringcentral://apiToken@host.test?from=0611223344'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing auth token' => ['ringcentral://@default?from=FROM'];
    }

    public static function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: from' => ['ringcentral://apiToken@default'];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'ringcentral://apiToken@default?from=0611223344'];
        yield [false, 'somethingElse://apiToken@default?from=0611223344'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://apiToken@default?from=0611223344'];
        yield ['somethingElse://apiToken@default']; // missing "from" option
    }
}
