<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Clickatell\Tests;

use Symfony\Component\Notifier\Bridge\Clickatell\ClickatellTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

class ClickatellTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): ClickatellTransportFactory
    {
        return new ClickatellTransportFactory();
    }

    public function createProvider(): iterable
    {
        yield [
            'clickatell://host.test?from=0611223344',
            'clickatell://authtoken@host.test?from=0611223344',
        ];
    }

    public function supportsProvider(): iterable
    {
        yield [true, 'clickatell://authtoken@default?from=0611223344'];
        yield [false, 'somethingElse://authtoken@default?from=0611223344'];
    }

    public function incompleteDsnProvider(): iterable
    {
        yield 'missing auth token' => ['clickatell://host?from=FROM'];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://authtoken@default?from=FROM'];
        yield ['somethingElse://authtoken@default']; // missing "from" option
    }
}
